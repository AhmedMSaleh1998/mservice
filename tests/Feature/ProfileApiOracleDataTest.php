<?php

namespace Tests\Feature;

use App\Services\Oracle\OracleDoctorDataLookupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Modules\Core\Services\OrderService;
use Modules\Memberships\Services\MembershipService;
use Modules\Users\Models\User;
use Tests\TestCase;

class ProfileApiOracleDataTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/profile-api-oracle-data.sqlite');

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
        Mockery::close();
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_profile_show_enriches_missing_membership_data_from_oracle(): void
    {
        $user = User::query()->create([
            'name' => '',
            'phone' => '01012345678',
            'email' => 'profile@example.com',
            'password' => bcrypt('secret123'),
            'national_id' => '29901011234567',
            'reg_number' => '213934',
            'active' => true,
            'lang' => 'ar',
            'notification_enabled' => true,
        ]);

        Sanctum::actingAs($user);

        $oracleService = Mockery::mock(OracleDoctorDataLookupService::class);
        $oracleService->shouldReceive('findByRegisterNo')
            ->once()
            ->with('213934')
            ->andReturn([
                'id' => 213714,
                'register_no' => '213934',
                'doctor_name' => 'احمد ممدوح احمد قنديل',
                'specialization_arabic_name' => 'طب حالات حرجة',
                'consult_id' => 4,
                'consult_name' => 'إستشاري',
            ]);

        $orderService = Mockery::mock(OrderService::class);

        app()->instance(MembershipService::class, new MembershipService($orderService, $oracleService));

        $response = $this
            ->withHeaders(['lang' => 'ar'])
            ->getJson('/api/v1/profile/show');

        $response->assertOk();
        $response->assertJsonPath('data.name', 'احمد ممدوح احمد قنديل');
        $response->assertJsonPath('data.reg_number', '213934');
        $response->assertJsonPath('data.membership_profile.full_name', 'احمد ممدوح احمد قنديل');
        $response->assertJsonPath('data.membership_profile.specialty', 'طب حالات حرجة');
        $response->assertJsonPath('data.membership_profile.degree', 'إستشاري');
        $response->assertJsonPath('data.membership_profile.registration_number', '213934');
        $response->assertJsonPath('data.oracle_profile.id', 213714);
        $response->assertJsonPath('data.oracle_profile.register_no', '213934');
        $response->assertJsonPath('data.oracle_profile.consult_id', 4);
        $response->assertJsonPath('data.oracle_profile.consult_name', 'إستشاري');
    }

    private function createTables(): void
    {
        Schema::connection('sqlite')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('password');
            $table->string('national_id')->nullable();
            $table->string('reg_number')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->boolean('active')->default(true);
            $table->string('lang')->nullable();
            $table->boolean('notification_enabled')->default(true);
            $table->string('address')->nullable();
            $table->string('neqaba_address')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('media', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('model_id');
            $table->string('model_type');
            $table->uuid('uuid')->nullable();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable();
            $table->timestamps();
        });
    }
}
