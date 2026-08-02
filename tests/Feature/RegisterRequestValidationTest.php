<?php

namespace Tests\Feature;

use App\Http\Requests\Api\RegisterRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RegisterRequestValidationTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/register-request-validation.sqlite');

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
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_register_request_rejects_duplicate_email_before_hitting_database_insert(): void
    {
        $this->insertExistingUser([
            'email' => 'email@email.com',
        ]);

        $request = RegisterRequest::create('/api/v1/auth/register', 'POST', $this->validPayload([
            'email' => 'email@email.com',
        ]));
        $request->setContainer(app());

        $validator = Validator::make(
            $request->all(),
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        );

        $this->assertFalse($validator->passes());
        $this->assertSame(
            ['This email address is already registered.'],
            $validator->errors()->get('email'),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'John Doe',
            'phone' => '01026513699',
            'password' => '123456789',
            'password_confirmation' => '123456789',
            'national_id' => '28501160105075',
            'reg_number' => '213934',
            'email' => 'john.doe@example.com',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertExistingUser(array $overrides = []): void
    {
        DB::table('users')->insert(array_merge([
            'name' => 'Existing User',
            'phone' => '01000000000',
            'email' => 'existing@example.com',
            'password' => bcrypt('secret123'),
            'national_id' => '29901011234567',
            'reg_number' => '123456',
            'role_id' => 3,
            'active' => true,
            'notification_enabled' => true,
            'lang' => 'en',
            'address' => null,
            'neqaba_address' => null,
            'remember_token' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function createUsersTable(): void
    {
        Schema::connection('sqlite')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone', 20)->nullable()->index();
            $table->string('email')->unique()->nullable();
            $table->string('national_id', 50)->nullable()->index();
            $table->string('reg_number', 50)->nullable();
            $table->unsignedBigInteger('role_id')->default(3);
            $table->string('password');
            $table->boolean('active')->default(false);
            $table->boolean('notification_enabled')->default(true);
            $table->string('lang')->nullable();
            $table->string('address')->nullable();
            $table->string('neqaba_address')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }
}
