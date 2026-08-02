<?php

namespace Tests\Feature;

use App\Services\Oracle\OracleDoctorExistenceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Modules\Users\Dto\RegisterDTO;
use Modules\Users\Services\AuthService;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Tests\TestCase;

class AuthServiceRegisterOracleTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/auth-service-register-oracle.sqlite');

        if (! is_dir(dirname($this->databasePath))) {
            mkdir(dirname($this->databasePath), 0777, true);
        }

        if (! file_exists($this->databasePath)) {
            touch($this->databasePath);
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $this->databasePath);

        DB::purge('sqlite');
        DB::disconnect('sqlite');
        $this->createUsersTable();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_register_creates_user_when_oracle_confirms_doctor_exists(): void
    {
        $oracleService = Mockery::mock(OracleDoctorExistenceService::class);
        $oracleService->shouldReceive('doctorExists')
            ->once()
            ->with('12345', '29901011234567')
            ->andReturnTrue();

        $user = $this->makeAuthService($oracleService)->register($this->makeDto());

        $this->assertDatabaseCount('users', 1);
        $this->assertNotNull($user->id);
        $this->assertSame('Doctor Test', $user->name);
        $this->assertSame('201123456789', $user->phone);
        $this->assertSame('29901011234567', $user->national_id);
        $this->assertSame('12345', $user->reg_number);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_register_rejects_user_when_oracle_does_not_find_doctor(): void
    {
        $oracleService = Mockery::mock(OracleDoctorExistenceService::class);
        $oracleService->shouldReceive('doctorExists')
            ->once()
            ->with('12345', '29901011234567')
            ->andReturnFalse();

        try {
            $this->makeAuthService($oracleService)->register($this->makeDto());
            $this->fail('Registration should fail when the doctor is missing from syndicate records.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                [__('No doctor record matches the provided registration number and national ID in syndicate records.')],
                $exception->errors()['reg_number'] ?? [],
            );
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_returns_service_unavailable_when_oracle_lookup_fails(): void
    {
        $oracleService = Mockery::mock(OracleDoctorExistenceService::class);
        $oracleService->shouldReceive('doctorExists')
            ->once()
            ->with('12345', '29901011234567')
            ->andThrow(new RuntimeException('Oracle is unavailable.'));

        try {
            $this->makeAuthService($oracleService)->register($this->makeDto());
            $this->fail('Registration should fail when Oracle lookup is unavailable.');
        } catch (ServiceUnavailableHttpException $exception) {
            $this->assertSame(
                __('Unable to verify doctor data with Oracle at the moment. Please try again later.'),
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_register_rejects_pending_duplicate_phone_without_creating_another_user(): void
    {
        DB::table('users')->insert([
            'name' => 'Pending User',
            'phone' => '201123456789',
            'email' => 'pending@example.com',
            'password' => bcrypt('secret123'),
            'national_id' => '29901011234567',
            'reg_number' => '12345',
            'active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $oracleService = Mockery::mock(OracleDoctorExistenceService::class);
        $oracleService->shouldNotReceive('doctorExists');

        try {
            $this->makeAuthService($oracleService)->register($this->makeDto());
            $this->fail('Registration should reject a pending duplicate phone.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                [__('A verification code has already been sent to this phone number. Please verify the account or request a new code.')],
                $exception->errors()['phone'] ?? [],
            );
        }

        $this->assertDatabaseCount('users', 1);
    }

    private function makeAuthService(OracleDoctorExistenceService $oracleService): AuthService
    {
        return new AuthService($oracleService);
    }

    private function makeDto(): RegisterDTO
    {
        return new RegisterDTO(
            name: 'Doctor Test',
            email: 'doctor@example.com',
            phone: '01123456789',
            nationalId: '29901011234567',
            regNumber: '12345',
            password: 'secret123',
        );
    }

    private function createUsersTable(): void
    {
        Schema::connection('sqlite')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('password');
            $table->string('national_id');
            $table->string('reg_number');
            $table->boolean('active')->default(true);
            $table->string('lang')->nullable();
            $table->boolean('notification_enabled')->default(true);
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('address')->nullable();
            $table->string('neqaba_address')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
}
