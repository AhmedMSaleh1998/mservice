<?php

namespace Tests\Feature;

use App\Http\Requests\Api\ChangePhoneRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Modules\Users\Models\User;
use Tests\TestCase;

class ChangePhoneRequestValidationTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/change-phone-request-validation.sqlite');

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
        Auth::logout();
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_old_phone_accepts_equivalent_phone_format(): void
    {
        $user = $this->createUser(['phone' => '201026513696']);
        Auth::login($user);

        $validator = $this->validator([
            'password' => 'secret123',
            'old_phone' => '+2001026513696',
            'new_phone' => '01026513697',
        ], $user);

        $this->assertTrue($validator->passes());
    }

    public function test_new_phone_rejects_duplicate_equivalent_phone_format(): void
    {
        $user = $this->createUser(['phone' => '201026513696']);
        $this->createUser([
            'phone' => '201026513697',
            'email' => 'other@example.com',
        ]);
        Auth::login($user);

        $validator = $this->validator([
            'password' => 'secret123',
            'old_phone' => '01026513696',
            'new_phone' => '+2001026513697',
        ], $user);

        $this->assertFalse($validator->passes());
        $this->assertSame(
            ['This phone number is already in use.'],
            $validator->errors()->get('new_phone'),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validator(array $payload, User $user): \Illuminate\Validation\Validator
    {
        $request = ChangePhoneRequest::create('/api/v1/auth/change-phone', 'POST', $payload);
        $request->setContainer(app());
        $request->setUserResolver(static fn (): User => $user);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        $request->withValidator($validator);

        return $validator;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createUser(array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Test User',
            'phone' => '201026513696',
            'email' => 'test@example.com',
            'password' => 'secret123',
        ], $overrides));
    }
}
