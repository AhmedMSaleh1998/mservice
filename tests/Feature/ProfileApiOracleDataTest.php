<?php

namespace Tests\Feature;

use App\Services\Oracle\OracleDoctorDataLookupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Modules\Core\Services\OrderService;
use Modules\MedicalGuide\Models\MedicalGuide;
use Modules\MedicalGuide\Models\MedicalGuidePlace;
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

        // No medical guide record synced for this doctor yet.
        $response->assertJsonPath('data.clinics', []);
        $response->assertJsonPath('data.specialties', []);
        $response->assertJsonPath('data.membership_status', null);
    }

    public function test_profile_show_returns_the_doctors_clinics_and_specialties_from_the_synced_record(): void
    {
        $user = $this->createDoctor('99793');
        $this->fakeOracleProfile();

        $guide = MedicalGuide::query()->create([
            'reg_number' => '99793',
            'title' => ['ar' => 'الحسنين محمد محمود محمد'],
            'description' => ['ar' => 'الطب النفسي'],
            'is_active' => true,
            'oracle_payload' => [
                'REGISTER_NO' => 99793,
                'specialization' => [
                    [
                        'general' => 'الطب النفسي',
                        'main_sub' => 'الطب النفسي',
                        'detail' => 'الطب النفسي',
                        'consultant' => 'إستشاري أول',
                        'reg_year' => '2017-08-07T00:00:00',
                    ],
                    [
                        'general' => 'الأمراض العصبية',
                        'main_sub' => '',
                        'detail' => null,
                        'consultant' => '',
                        'reg_year' => null,
                    ],
                    // An item with no name at any level is skipped.
                    ['general' => '', 'consultant' => 'أخصائي'],
                ],
                'clinics' => null,
            ],
        ]);
        MedicalGuidePlace::query()->create([
            'medical_guide_id' => $guide->id,
            'name' => ['ar' => 'عيادة'],
            'address' => ['ar' => '24 ش الجولف'],
            'phones' => ['0106665656', '022254544'],
            'is_active' => true,
        ]);

        $response = $this->withHeaders(['lang' => 'ar'])->getJson('/api/v1/profile/show');

        $response->assertOk();
        $response->assertJsonPath('data.specialties', [
            ['name' => 'الطب النفسي', 'degree' => 'إستشاري أول', 'date' => '07/08/2017'],
            ['name' => 'الأمراض العصبية', 'degree' => null, 'date' => null],
        ]);
        $response->assertJsonCount(1, 'data.clinics');
        $response->assertJsonPath('data.clinics.0.address', '24 ش الجولف');
        $response->assertJsonPath('data.clinics.0.phones', ['0106665656', '022254544']);
        $response->assertJsonPath('data.membership_status', null);
    }

    public function test_profile_show_returns_empty_specialties_when_the_record_has_none(): void
    {
        $this->createDoctor('172539');
        $this->fakeOracleProfile();

        MedicalGuide::query()->create([
            'reg_number' => '172539',
            'title' => ['ar' => 'طبيب'],
            'is_active' => true,
            'oracle_payload' => ['REGISTER_NO' => 172539, 'specialization' => null, 'clinics' => null],
        ]);

        $this->getJson('/api/v1/profile/show')
            ->assertOk()
            ->assertJsonPath('data.specialties', [])
            ->assertJsonPath('data.clinics', []);
    }

    private function createDoctor(string $regNumber): User
    {
        $user = User::query()->create([
            'name' => 'Doctor',
            'phone' => '01012345678',
            'email' => 'doctor@example.com',
            'password' => bcrypt('secret123'),
            'national_id' => '29901011234567',
            'reg_number' => $regNumber,
            'active' => true,
            'lang' => 'ar',
            'notification_enabled' => true,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    private function fakeOracleProfile(): void
    {
        $oracleService = Mockery::mock(OracleDoctorDataLookupService::class);
        $oracleService->shouldReceive('findByRegisterNo')->andReturn(null);

        app()->instance(MembershipService::class, new MembershipService(Mockery::mock(OrderService::class), $oracleService));
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

        Schema::connection('sqlite')->create('medical_guides', function (Blueprint $table): void {
            $table->id();
            $table->string('reg_number')->nullable();
            $table->json('title');
            $table->json('description')->nullable();
            $table->unsignedBigInteger('specialty_id')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('oracle_payload')->nullable();
            $table->timestamp('oracle_synced_at')->nullable();
            $table->timestamp('oracle_last_changed_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('medical_guide_places', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('medical_guide_id');
            $table->json('name');
            $table->json('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->json('phones')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
