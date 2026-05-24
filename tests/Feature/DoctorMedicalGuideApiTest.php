<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Modules\Users\Models\User;
use Tests\TestCase;

class DoctorMedicalGuideApiTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/doctor-medical-guide.sqlite');

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

        $this->createTables();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_show_returns_clear_message_when_user_has_no_registration_number(): void
    {
        Sanctum::actingAs($this->seedUser(['reg_number' => null]));

        $this->getJson('/api/v1/medical-guides/me/')
            ->assertNotFound()
            ->assertJsonPath('message', 'Your account does not have a registration number, so we cannot find your medical guide.')
            ->assertJsonPath('error', 'Not Found');
    }

    public function test_show_returns_clear_message_when_registration_number_has_no_medical_guide(): void
    {
        Sanctum::actingAs($this->seedUser(['reg_number' => '12345']));

        $this->getJson('/api/v1/medical-guides/me/')
            ->assertOk()
            ->assertJsonPath('message', 'لا يوجد دليل طبي')
            ->assertJsonPath('medical_guide', null);
    }

    private function createTables(): void
    {
        Schema::dropIfExists('medical_guides');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('national_id', 50)->nullable();
            $table->string('reg_number', 50)->nullable();
            $table->tinyInteger('role_id')->default(3);
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('medical_guides', function (Blueprint $table): void {
            $table->id();
            $table->string('reg_number', 100)->nullable()->unique();
            $table->json('title')->nullable();
            $table->json('description')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    private function seedUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Test Doctor',
            'phone' => '01000000000',
            'email' => 'doctor@example.com',
            'national_id' => '29901011230000',
            'reg_number' => '90001',
            'password' => 'password',
            'active' => true,
        ], $attributes));
    }
}
