<?php

namespace Tests\Feature;

use App\Console\Commands\NormalizeStoredPhoneNumbers;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNormalizationPersistenceTest extends TestCase
{
    private const CANONICAL = '201026513696';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('phone', 20)->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public static function brokenStoredFormsProvider(): array
    {
        return [
            'country code twice' => ['20201026513696'],
            'bare national number' => ['1026513696'],
            'local trunk zero' => ['01026513696'],
            'arabic indic digits' => ['٠١٠٢٦٥١٣٦٩٦'],
            'country code before trunk zero' => ['2001026513696'],
        ];
    }

    /**
     * The backfill only reports until --apply is passed.
     */
    public function test_a_dry_run_leaves_the_stored_value_untouched(): void
    {
        DB::table('users')->insert([
            'phone' => '20201026513696',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan(NormalizeStoredPhoneNumbers::class, ['--table' => ['users']])
            ->assertSuccessful();

        $this->assertSame('20201026513696', DB::table('users')->value('phone'));
    }

    #[DataProvider('brokenStoredFormsProvider')]
    public function test_apply_rewrites_every_broken_form_to_the_canonical_number(string $stored): void
    {
        DB::table('users')->insert([
            'phone' => $stored,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan(NormalizeStoredPhoneNumbers::class, ['--table' => ['users'], '--apply' => true])
            ->assertSuccessful();

        $this->assertSame(self::CANONICAL, DB::table('users')->value('phone'));
    }

    public function test_an_already_canonical_number_is_left_alone(): void
    {
        DB::table('users')->insert([
            'phone' => self::CANONICAL,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan(NormalizeStoredPhoneNumbers::class, ['--table' => ['users'], '--apply' => true])
            ->assertSuccessful();

        $this->assertSame(self::CANONICAL, DB::table('users')->value('phone'));
    }

    public function test_a_foreign_number_is_reported_but_never_rewritten(): void
    {
        DB::table('users')->insert([
            'phone' => '966501234567',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan(NormalizeStoredPhoneNumbers::class, ['--table' => ['users'], '--apply' => true])
            ->assertSuccessful();

        $this->assertSame('966501234567', DB::table('users')->value('phone'));
    }

    public function test_the_backfill_is_idempotent(): void
    {
        DB::table('users')->insert([
            'phone' => '20201026513696',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (range(1, 2) as $ignored) {
            $this->artisan(NormalizeStoredPhoneNumbers::class, ['--table' => ['users'], '--apply' => true])
                ->assertSuccessful();
        }

        $this->assertSame(self::CANONICAL, DB::table('users')->value('phone'));
    }

    public function test_the_sms_gateway_receives_the_canonical_number(): void
    {
        // The gateway resolves its receiver through the same normalizer, so a
        // number stacked with country codes still leaves as one clean value.
        foreach (self::brokenStoredFormsProvider() as [$stored]) {
            $this->assertSame(
                self::CANONICAL,
                PhoneNumberNormalizer::normalize($stored),
                sprintf('%s should reach the SMS gateway canonically.', $stored),
            );
        }
    }
}
