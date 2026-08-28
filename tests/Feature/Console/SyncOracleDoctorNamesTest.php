<?php

namespace Tests\Feature\Console;

use App\Services\Oracle\OracleDoctorDataLookupService;
use App\Services\Oracle\OracleDoctorExistenceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Modules\Users\Models\User;
use Tests\TestCase;

class SyncOracleDoctorNamesTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/sync-oracle-doctor-names.sqlite');

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
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('national_id')->nullable();
            $table->string('reg_number')->nullable();
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
        return User::query()->create(array_merge([
            'name' => 'Ahmed Samir',
            'phone' => '01000000001',
            'password' => 'secret',
            'national_id' => '29411271302371',
            'reg_number' => '307557',
        ], $attributes));
    }

    private function mockOracle(bool $exists, ?array $profile): void
    {
        $existence = Mockery::mock(OracleDoctorExistenceService::class);
        $existence->shouldReceive('doctorExists')->andReturn($exists);
        $this->app->instance(OracleDoctorExistenceService::class, $existence);

        $lookup = Mockery::mock(OracleDoctorDataLookupService::class);
        $lookup->shouldReceive('findByRegisterNo')->andReturn($profile);
        $this->app->instance(OracleDoctorDataLookupService::class, $lookup);
    }

    public function test_verified_user_gets_the_oracle_name_with_apply(): void
    {
        $user = $this->makeUser();
        $this->mockOracle(true, ['doctor_name' => 'احمد سمير محمد علي']);

        $this->artisan('users:sync-oracle-names', ['--apply' => true])->assertSuccessful();

        $this->assertSame('احمد سمير محمد علي', $user->fresh()->name);
    }

    public function test_report_only_run_changes_nothing(): void
    {
        $user = $this->makeUser();
        $this->mockOracle(true, ['doctor_name' => 'احمد سمير محمد علي']);

        $this->artisan('users:sync-oracle-names')->assertSuccessful();

        $this->assertSame('Ahmed Samir', $user->fresh()->name);
    }

    public function test_identity_mismatch_is_never_rewritten(): void
    {
        $user = $this->makeUser();
        $this->mockOracle(false, ['doctor_name' => 'اسم شخص اخر تماما']);

        $this->artisan('users:sync-oracle-names', ['--apply' => true])->assertSuccessful();

        $this->assertSame('Ahmed Samir', $user->fresh()->name);
    }

    public function test_record_without_oracle_name_is_left_untouched(): void
    {
        $user = $this->makeUser();
        $this->mockOracle(true, ['doctor_name' => null]);

        $this->artisan('users:sync-oracle-names', ['--apply' => true])->assertSuccessful();

        $this->assertSame('Ahmed Samir', $user->fresh()->name);
    }

    public function test_user_without_national_id_is_skipped_entirely(): void
    {
        $user = $this->makeUser(['national_id' => null]);

        $existence = Mockery::mock(OracleDoctorExistenceService::class);
        $existence->shouldNotReceive('doctorExists');
        $this->app->instance(OracleDoctorExistenceService::class, $existence);

        $lookup = Mockery::mock(OracleDoctorDataLookupService::class);
        $lookup->shouldNotReceive('findByRegisterNo');
        $this->app->instance(OracleDoctorDataLookupService::class, $lookup);

        $this->artisan('users:sync-oracle-names', ['--apply' => true])->assertSuccessful();

        $this->assertSame('Ahmed Samir', $user->fresh()->name);
    }
}
