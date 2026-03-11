<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Modules\Users\Models\RegistrationRequest;
use Modules\Users\Services\UpdateRegistrationRequestService;
use Tests\TestCase;

class UpdateRegistrationRequestServiceTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/update-registration-request-service.sqlite');

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

    public function test_update_sanitizes_control_characters_in_uploaded_document_names(): void
    {
        $registrationRequest = RegistrationRequest::query()->create([
            'national_id' => '30001011234567',
            'full_name_ar' => 'اختبار الطبيب',
            'full_name_en' => 'Doctor Test',
            'status' => RegistrationRequest::STATUS_PENDING_REVIEW,
            'active' => false,
            'documents' => [],
        ]);

        $unsafeFileName = "graduation_certificate_image_‎⁨شهادة التخرج⁩.jpeg";
        $expectedPath = null;

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getClientOriginalName')->once()->andReturn($unsafeFileName);
        $file->shouldReceive('storeAs')
            ->once()
            ->with(
                "documents/{$registrationRequest->id}",
                Mockery::on(function (string $filename) use (&$expectedPath, $registrationRequest): bool {
                    $this->assertMatchesRegularExpression('/^\d+_graduation_certificate_image_/', $filename);
                    $this->assertDoesNotMatchRegularExpression('/\p{C}/u', $filename);
                    $this->assertStringContainsString('graduation_certificate_image_شهادة التخرج.jpeg', $filename);

                    $expectedPath = "documents/{$registrationRequest->id}/{$filename}";

                    return true;
                }),
                'public',
            )
            ->andReturnUsing(function (string $directory, string $filename, string $disk): string {
                $path = "{$directory}/{$filename}";
                Storage::disk($disk)->put($path, 'sanitized file');

                return $path;
            });

        $updatedRegistrationRequest = app(UpdateRegistrationRequestService::class)->update(
            $registrationRequest,
            [],
            ['graduation_certificate_image' => $file],
        );

        $this->assertNotNull($expectedPath);
        $this->assertSame($expectedPath, $updatedRegistrationRequest->documents['graduation_certificate_image']);
        Storage::disk('public')->assertExists($expectedPath);
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
