<?php

namespace Tests\Feature\Support;

use App\Support\DuplicateUserAccountMerger;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Users\Models\User;
use Tests\TestCase;

class DuplicateUserAccountMergerTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/duplicate-user-account-merger.sqlite');

        if (! is_dir(dirname($this->databasePath))) {
            mkdir(dirname($this->databasePath), 0777, true);
        }

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        touch($this->databasePath);

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $this->databasePath);

        DB::purge('sqlite');
        DB::disconnect('sqlite');

        Schema::connection('sqlite')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->string('national_id')->nullable();
            $table->string('reg_number')->nullable();
            $table->boolean('active')->default(false);
            $table->timestamps();
        });

        foreach (['orders', 'rest_unit_bookings', 'course_bookings', 'travel_bookings', 'certificate_requests', 'support_tickets', 'ad_requests', 'membership_requests', 'user_addresses', 'audit_logs', 'blogs'] as $tableName) {
            Schema::connection('sqlite')->create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }

        Schema::connection('sqlite')->create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    private function makeUser(array $attributes = []): User
    {
        static $counter = 0;
        $counter++;

        return User::query()->create(array_merge([
            'name' => 'User ' . $counter,
            'phone' => '0100000000' . $counter,
            'password' => 'secret',
            'national_id' => '2940000000000' . $counter,
            'reg_number' => '111111',
            'active' => false,
        ], $attributes));
    }

    private function giveToken(User $user, ?string $lastUsedAt): void
    {
        static $tokenCounter = 0;
        $tokenCounter++;

        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => $user->getMorphClass(),
            'tokenable_id' => $user->id,
            'name' => 'auth_token',
            'token' => str_pad((string) $tokenCounter, 64, 'a'),
            'last_used_at' => $lastUsedAt,
            'created_at' => now()->subDays(10),
        ]);
    }

    private function merge(): array
    {
        return app(DuplicateUserAccountMerger::class)->merge();
    }

    public function test_single_active_account_survives_even_when_older(): void
    {
        $activeOld = $this->makeUser(['active' => true, 'created_at' => now()->subDays(5)]);
        $inactiveNew = $this->makeUser(['created_at' => now()]);
        DB::table('orders')->insert(['user_id' => $inactiveNew->id]);

        $stats = $this->merge();

        $this->assertNotNull(User::find($activeOld->id));
        $this->assertNull(User::find($inactiveNew->id));
        $this->assertSame($activeOld->id, (int) DB::table('orders')->value('user_id'));
        $this->assertSame(1, $stats['accounts_deleted']);
        $this->assertSame(1, $stats['rows_migrated']);
    }

    public function test_active_account_with_recent_session_survives_over_newer_active(): void
    {
        $activeOldWithSession = $this->makeUser(['active' => true, 'created_at' => now()->subDays(5)]);
        $activeNewNoSession = $this->makeUser(['active' => true, 'created_at' => now()]);
        $this->giveToken($activeOldWithSession, now()->subDay()->toDateTimeString());
        DB::table('orders')->insert(['user_id' => $activeNewNoSession->id]);
        DB::table('membership_requests')->insert(['user_id' => $activeNewNoSession->id]);

        $this->merge();

        $this->assertNotNull(User::find($activeOldWithSession->id));
        $this->assertNull(User::find($activeNewNoSession->id));
        $this->assertSame($activeOldWithSession->id, (int) DB::table('orders')->value('user_id'));
        $this->assertSame($activeOldWithSession->id, (int) DB::table('membership_requests')->value('user_id'));
    }

    public function test_most_recent_session_wins_when_both_actives_have_sessions(): void
    {
        $activeOld = $this->makeUser(['active' => true, 'created_at' => now()->subDays(5)]);
        $activeNew = $this->makeUser(['active' => true, 'created_at' => now()]);
        $this->giveToken($activeOld, now()->subHours(2)->toDateTimeString());
        $this->giveToken($activeNew, now()->subDays(3)->toDateTimeString());

        $this->merge();

        $this->assertNotNull(User::find($activeOld->id));
        $this->assertNull(User::find($activeNew->id));
    }

    public function test_newest_active_survives_when_no_sessions_exist(): void
    {
        $activeOld = $this->makeUser(['active' => true, 'created_at' => now()->subDays(5)]);
        $activeNew = $this->makeUser(['active' => true, 'created_at' => now()]);

        $this->merge();

        $this->assertNull(User::find($activeOld->id));
        $this->assertNotNull(User::find($activeNew->id));
    }

    public function test_newest_survives_when_all_inactive(): void
    {
        $old = $this->makeUser(['created_at' => now()->subDays(5)]);
        $middle = $this->makeUser(['created_at' => now()->subDays(3)]);
        $newest = $this->makeUser(['created_at' => now()]);

        $this->merge();

        $this->assertNull(User::find($old->id));
        $this->assertNull(User::find($middle->id));
        $this->assertNotNull(User::find($newest->id));
    }

    public function test_deleted_account_login_tokens_are_removed(): void
    {
        $survivor = $this->makeUser(['active' => true, 'created_at' => now()->subDays(5)]);
        $this->giveToken($survivor, now()->toDateTimeString());
        $loser = $this->makeUser(['active' => true, 'created_at' => now()]);
        $this->giveToken($loser, now()->subDays(4)->toDateTimeString());

        $this->merge();

        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $loser->id)->count());
        $this->assertSame(1, DB::table('personal_access_tokens')->where('tokenable_id', $survivor->id)->count());
    }

    public function test_accounts_without_reg_number_are_never_touched(): void
    {
        $admin = $this->makeUser(['name' => 'admin', 'reg_number' => null, 'active' => true]);
        $adminTwin = $this->makeUser(['name' => 'admin 2', 'reg_number' => '', 'active' => true]);

        $stats = $this->merge();

        $this->assertNotNull(User::find($admin->id));
        $this->assertNotNull(User::find($adminTwin->id));
        $this->assertSame(0, $stats['groups']);
    }
}
