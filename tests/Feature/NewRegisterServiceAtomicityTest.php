<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Modules\Users\Dto\NewRegisterDTO;
use Modules\Users\Services\NewRegisterService;
use RuntimeException;
use Tests\TestCase;

class NewRegisterServiceAtomicityTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/new-register-service.sqlite');

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
        Storage::fake('public');
        Storage::disk('public')->deleteDirectory('documents');

        $this->createRegistrationRequestsTable();
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

    public function test_register_persists_request_and_documents_when_all_uploads_succeed(): void
    {
        $registrationRequest = app(NewRegisterService::class)->register($this->makeDto());

        $this->assertDatabaseCount('registration_requests', 1);
        $this->assertNotNull($registrationRequest->id);
        $this->assertCount(6, $registrationRequest->documents);

        foreach ($registrationRequest->documents as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_register_rolls_back_and_deletes_partial_uploads_when_any_document_fails(): void
    {
        $service = app(NewRegisterService::class);

        try {
            $service->register($this->makeDto([
                'nationalIdImg' => $this->failingUploadedFile('national-id.jpg'),
            ]));

            $this->fail('The registration should fail when one document upload fails.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated upload failure.', $exception->getMessage());
        }

        $this->assertDatabaseCount('registration_requests', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_register_sanitizes_control_characters_in_uploaded_document_names(): void
    {
        $unsafeFileName = "CamScanner ١٠\u{200F}-٠٣\u{200F}-٢٠٢٦ ٠٦.٢٣.jpg";
        $expectedPath = null;

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getClientOriginalName')->once()->andReturn($unsafeFileName);
        $file->shouldReceive('storeAs')
            ->once()
            ->with(
                Mockery::pattern('/^documents\/\d+$/'),
                Mockery::on(function (string $filename) use (&$expectedPath): bool {
                    $this->assertMatchesRegularExpression('/^\d+_personal_image_/', $filename);
                    $this->assertDoesNotMatchRegularExpression('/\p{C}/u', $filename);
                    $this->assertStringContainsString('CamScanner ١٠-٠٣-٢٠٢٦ ٠٦.٢٣.jpg', $filename);

                    return true;
                }),
                'public',
            )
            ->andReturnUsing(function (string $directory, string $filename, string $disk) use (&$expectedPath): string {
                $path = "{$directory}/{$filename}";
                $expectedPath = $path;
                Storage::disk($disk)->put($path, 'sanitized file');

                return $path;
            });

        $registrationRequest = app(NewRegisterService::class)->register($this->makeDto([
            'personalImg' => $file,
        ]));

        $this->assertDatabaseCount('registration_requests', 1);
        $this->assertNotNull($expectedPath);
        $this->assertSame($expectedPath, $registrationRequest->documents['personal_image']);
        Storage::disk('public')->assertExists($expectedPath);
    }

    private function makeDto(array $overrides = []): NewRegisterDTO
    {
        $data = array_merge([
            'nationalId' => '30001011234567',
            'fullNameAr' => 'اختبار الطبيب',
            'fullNameEn' => 'Doctor Test',
            'gender' => 'male',
            'nationalityId' => 1,
            'religionId' => 1,
            'governorateId' => 1,
            'issuedFrom' => 'Cairo',
            'birthGovernorateId' => 1,
            'birthDate' => '1990-01-01',
            'residenceGovernorateId' => 1,
            'residenceCenter' => 'Nasr City',
            'residenceStreet' => 'Test Street',
            'residenceHouseNumber' => '12',
            'residencePhone' => '12345678',
            'residenceMobile1CountryCode' => '+20',
            'residenceMobile1' => '01123456789',
            'residenceMobile2CountryCode' => '+20',
            'residenceMobile2' => '01098765432',
            'email' => 'doctor@example.com',
            'universityId' => 1,
            'faculty' => 'Medicine',
            'graduationYear' => '2014',
            'graduationMonth' => '06',
            'gradeId' => 1,
            'firstForeignLanguageId' => 1,
            'secondForeignLanguageId' => 2,
            'personalImg' => UploadedFile::fake()->image('personal.jpg'),
            'nationalIdImg' => UploadedFile::fake()->image('national-id.jpg'),
            'graduationCertificateImg' => UploadedFile::fake()->image('graduation.jpg'),
            'internshipCertificateImg' => UploadedFile::fake()->image('internship.jpg'),
            'criminalRecordCertificateImg' => UploadedFile::fake()->image('criminal-record.jpg'),
            'dobImg' => UploadedFile::fake()->image('dob.jpg'),
        ], $overrides);

        return new NewRegisterDTO(
            nationalId: $data['nationalId'],
            fullNameAr: $data['fullNameAr'],
            fullNameEn: $data['fullNameEn'],
            gender: $data['gender'],
            nationalityId: $data['nationalityId'],
            religionId: $data['religionId'],
            governorateId: $data['governorateId'],
            issuedFrom: $data['issuedFrom'],
            birthGovernorateId: $data['birthGovernorateId'],
            birthDate: $data['birthDate'],
            residenceGovernorateId: $data['residenceGovernorateId'],
            residenceCenter: $data['residenceCenter'],
            residenceStreet: $data['residenceStreet'],
            residenceHouseNumber: $data['residenceHouseNumber'],
            residencePhone: $data['residencePhone'],
            residenceMobile1CountryCode: $data['residenceMobile1CountryCode'],
            residenceMobile1: $data['residenceMobile1'],
            residenceMobile2CountryCode: $data['residenceMobile2CountryCode'],
            residenceMobile2: $data['residenceMobile2'],
            email: $data['email'],
            universityId: $data['universityId'],
            faculty: $data['faculty'],
            graduationYear: $data['graduationYear'],
            graduationMonth: $data['graduationMonth'],
            gradeId: $data['gradeId'],
            firstForeignLanguageId: $data['firstForeignLanguageId'],
            secondForeignLanguageId: $data['secondForeignLanguageId'],
            personalImg: $data['personalImg'],
            nationalIdImg: $data['nationalIdImg'],
            graduationCertificateImg: $data['graduationCertificateImg'],
            internshipCertificateImg: $data['internshipCertificateImg'],
            criminalRecordCertificateImg: $data['criminalRecordCertificateImg'],
            dobImg: $data['dobImg'],
        );
    }

    private function failingUploadedFile(string $fileName): UploadedFile
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getClientOriginalName')->andReturn($fileName);
        $file->shouldReceive('storeAs')->andThrow(new RuntimeException('Simulated upload failure.'));

        return $file;
    }

    private function createRegistrationRequestsTable(): void
    {
        Schema::connection('sqlite')->create('registration_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('national_id')->nullable();
            $table->string('full_name_ar')->nullable();
            $table->string('full_name_en')->nullable();
            $table->string('gender')->nullable();
            $table->unsignedBigInteger('nationality')->nullable();
            $table->unsignedBigInteger('religion')->nullable();
            $table->unsignedBigInteger('governorate')->nullable();
            $table->string('issued_from')->nullable();
            $table->unsignedBigInteger('birth_governorate')->nullable();
            $table->date('birth_date')->nullable();
            $table->unsignedBigInteger('residence_governorate')->nullable();
            $table->string('residence_center')->nullable();
            $table->string('residence_street')->nullable();
            $table->string('residence_house_number')->nullable();
            $table->string('residence_phone')->nullable();
            $table->string('residence_mobile_1_country_code')->nullable();
            $table->string('residence_mobile_1')->nullable();
            $table->string('residence_mobile_2_country_code')->nullable();
            $table->string('residence_mobile_2')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('university')->nullable();
            $table->string('faculty')->nullable();
            $table->string('graduation_year')->nullable();
            $table->string('graduation_month')->nullable();
            $table->unsignedBigInteger('grade')->nullable();
            $table->unsignedBigInteger('first_foreign_language')->nullable();
            $table->unsignedBigInteger('second_foreign_language')->nullable();
            $table->string('license_number')->nullable();
            $table->date('license_date')->nullable();
            $table->string('license_image')->nullable();
            $table->string('status')->nullable();
            $table->string('reg_code')->nullable();
            $table->string('oracle_register_no')->nullable();
            $table->boolean('active')->default(false);
            $table->json('documents')->nullable();
            $table->timestamps();
        });
    }
}
