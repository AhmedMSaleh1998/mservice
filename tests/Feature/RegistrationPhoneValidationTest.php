<?php

namespace Tests\Feature;

use App\Http\Requests\Api\NewRegisterRequest;
use App\Http\Requests\Api\RetrieveRegistrationDocumentsRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RegistrationPhoneValidationTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/registration-phone-validation.sqlite');

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
        $this->createValidationTables();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_new_registration_accepts_11_digit_mobile_numbers(): void
    {
        $this->seedRegistrationLookups();

        $request = NewRegisterRequest::create('/api/v1/register-request', 'POST', $this->validRegistrationPayload());
        $request->setContainer(app());

        foreach ($this->registrationFiles() as $key => $file) {
            $request->files->set($key, $file);
        }

        $validator = Validator::make(
            array_merge($request->all(), $request->allFiles()),
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        );

        $this->assertTrue($validator->passes(), json_encode($validator->errors()->toArray(), JSON_UNESCAPED_UNICODE));
    }

    public function test_new_registration_rejects_mobile_numbers_that_are_not_11_digits(): void
    {
        $this->seedRegistrationLookups();

        $request = NewRegisterRequest::create('/api/v1/register-request', 'POST', $this->validRegistrationPayload([
            'residence_mobile_1' => '1012345678',
            'residence_mobile_2' => '1098765432',
        ]));
        $request->setContainer(app());

        foreach ($this->registrationFiles() as $key => $file) {
            $request->files->set($key, $file);
        }

        $validator = Validator::make(
            array_merge($request->all(), $request->allFiles()),
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        );

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('residence_mobile_1', $validator->errors()->toArray());
        $this->assertArrayHasKey('residence_mobile_2', $validator->errors()->toArray());
    }

    public function test_retrieve_documents_accepts_11_digit_mobile_number(): void
    {
        $request = RetrieveRegistrationDocumentsRequest::create('/api/v1/register-request/retrieve-documents', 'POST', [
            'national_id' => '29901011234567',
            'residence_mobile_1_country_code' => '+20',
            'residence_mobile_1' => '01123456789',
        ]);
        $request->setContainer(app());

        $validator = Validator::make(
            $request->all(),
            $request->rules(),
            [],
            $request->attributes(),
        );

        $this->assertTrue($validator->passes(), json_encode($validator->errors()->toArray(), JSON_UNESCAPED_UNICODE));
    }

    public function test_retrieve_documents_rejects_mobile_number_shorter_than_11_digits(): void
    {
        $request = RetrieveRegistrationDocumentsRequest::create('/api/v1/register-request/retrieve-documents', 'POST', [
            'national_id' => '29901011234567',
            'residence_mobile_1_country_code' => '+20',
            'residence_mobile_1' => '1123456789',
        ]);
        $request->setContainer(app());

        $validator = Validator::make(
            $request->all(),
            $request->rules(),
            [],
            $request->attributes(),
        );

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('residence_mobile_1', $validator->errors()->toArray());
    }

    public function test_new_registration_rejects_email_longer_than_50_characters(): void
    {
        $this->seedRegistrationLookups();

        $request = NewRegisterRequest::create('/api/v1/register-request', 'POST', $this->validRegistrationPayload([
            'email' => 'verylongemailaddressfortestinglengthlimit12345@example.com',
        ]));
        $request->setContainer(app());

        foreach ($this->registrationFiles() as $key => $file) {
            $request->files->set($key, $file);
        }

        $validator = Validator::make(
            array_merge($request->all(), $request->allFiles()),
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        );

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_new_registration_rejects_non_numeric_graduation_year(): void
    {
        $this->seedRegistrationLookups();

        $request = NewRegisterRequest::create('/api/v1/register-request', 'POST', $this->validRegistrationPayload([
            'graduation_year' => 'سبتمبر2023',
        ]));
        $request->setContainer(app());

        foreach ($this->registrationFiles() as $key => $file) {
            $request->files->set($key, $file);
        }

        $validator = Validator::make(
            array_merge($request->all(), $request->allFiles()),
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        );

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('graduation_year', $validator->errors()->toArray());
    }

    public function test_new_registration_rejects_graduation_year_outside_allowed_range(): void
    {
        $this->seedRegistrationLookups();

        $request = NewRegisterRequest::create('/api/v1/register-request', 'POST', $this->validRegistrationPayload([
            'graduation_year' => (string) (now()->year + 1),
        ]));
        $request->setContainer(app());

        foreach ($this->registrationFiles() as $key => $file) {
            $request->files->set($key, $file);
        }

        $validator = Validator::make(
            array_merge($request->all(), $request->allFiles()),
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        );

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('graduation_year', $validator->errors()->toArray());
    }

    /**
     * @return array<string, mixed>
     */
    private function validRegistrationPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name_ar' => 'اختبار الطبيب',
            'full_name_en' => 'Doctor Test',
            'gender' => 'male',
            'nationality' => 1,
            'religion' => 1,
            'national_id' => 'A123456789',
            'issued_from' => 'Cairo',
            'governorate' => 1,
            'birth_date' => '1990-01-01',
            'birth_governorate' => 1,
            'residence_house_number' => '12',
            'residence_street' => 'Test Street',
            'residence_center' => 'Test Center',
            'residence_governorate' => 1,
            'residence_phone' => '1234567890',
            'residence_mobile_1_country_code' => '+20',
            'residence_mobile_1' => '01123456789',
            'residence_mobile_2_country_code' => '+20',
            'residence_mobile_2' => '01098765432',
            'email' => 'doctor@example.com',
            'faculty' => 'Medicine',
            'graduation_month' => '06',
            'graduation_year' => '2014',
            'university' => 1,
            'grade' => 1,
            'first_foreign_language' => 1,
            'second_foreign_language' => 2,
        ], $overrides);
    }

    /**
     * @return array<string, UploadedFile>
     */
    private function registrationFiles(): array
    {
        return [
            'personal_image' => UploadedFile::fake()->image('personal.jpg'),
            'national_id_image' => UploadedFile::fake()->image('national-id.jpg'),
            'graduation_certificate_image' => UploadedFile::fake()->image('graduation.jpg'),
            'internship_certificate_image' => UploadedFile::fake()->image('internship.jpg'),
            'criminal_record_certificate_image' => UploadedFile::fake()->image('criminal-record.jpg'),
            'dob_image' => UploadedFile::fake()->image('dob.jpg'),
        ];
    }

    private function seedRegistrationLookups(): void
    {
        DB::table('nationalities')->insert([
            'id' => 1,
            'code' => 840,
            'name' => json_encode(['ar' => 'أمريكي', 'en' => 'American'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('religions')->insert([
            'id' => 1,
            'name' => json_encode(['ar' => 'مسلم', 'en' => 'Muslim'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('provinces')->insert([
            'id' => 1,
            'code' => 1,
            'name' => json_encode(['ar' => 'القاهرة', 'en' => 'Cairo'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('medical_universities')->insert([
            'id' => 1,
            'code' => 1,
            'name' => json_encode(['ar' => 'جامعة القاهرة', 'en' => 'Cairo University'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('grades')->insert([
            'id' => 1,
            'code' => 1,
            'name' => json_encode(['ar' => 'جيد جدًا', 'en' => 'Very Good'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('languages')->insert([
            [
                'id' => 1,
                'name' => json_encode(['ar' => 'الإنجليزية', 'en' => 'English'], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => json_encode(['ar' => 'الفرنسية', 'en' => 'French'], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function createValidationTables(): void
    {
        Schema::connection('sqlite')->create('registration_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('national_id')->nullable();
            $table->string('residence_phone', 25)->nullable();
            $table->string('residence_mobile_1', 25)->nullable();
            $table->string('residence_mobile_1_country_code', 8)->nullable();
            $table->string('residence_mobile_2', 25)->nullable();
            $table->string('residence_mobile_2_country_code', 8)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('nationalities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('code')->nullable();
            $table->json('name');
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('religions', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('code')->nullable();
            $table->json('name');
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('medical_universities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('code')->nullable();
            $table->json('name');
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('grades', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('code')->nullable();
            $table->json('name');
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('languages', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->timestamps();
        });
    }
}
