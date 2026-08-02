<?php

namespace Tests\Feature;

use App\Events\UserRegistered;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Requests\Api\RegisterRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Modules\Users\Models\User;
use Modules\Users\Services\AuthService;
use RuntimeException;
use Tests\TestCase;

class RegisterControllerAtomicityTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/register-controller-atomicity.sqlite');

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

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone', 20)->nullable()->index();
            $table->string('email')->unique()->nullable();
            $table->string('password');
            $table->timestamps();
        });
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

    public function test_register_rolls_back_user_when_otp_event_fails(): void
    {
        $authService = Mockery::mock(AuthService::class);
        $authService->shouldReceive('register')
            ->once()
            ->andReturnUsing(function (): User {
                return User::query()->create([
                    'name' => 'Doctor Test',
                    'phone' => '201123456789',
                    'email' => 'doctor@example.com',
                    'password' => 'secret123',
                ]);
            });

        Event::listen(UserRegistered::class, static function (): void {
            throw new RuntimeException('Unable to send verification code.');
        });

        $controller = new RegisterController($authService);
        $request = RegisterRequest::create('/api/v1/auth/register', 'POST', [
            'name' => 'Doctor Test',
            'email' => 'doctor@example.com',
            'phone' => '01123456789',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'national_id' => '29901011234567',
            'reg_number' => '12345',
        ]);

        try {
            $controller->register($request);
            $this->fail('Registration should fail when OTP event fails.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unable to send verification code.', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }
}
