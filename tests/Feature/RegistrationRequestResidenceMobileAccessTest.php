<?php

namespace Tests\Feature;

use App\Filament\Resources\RegistrationRequests\Schemas\RegistrationRequestForm;
use App\Models\Admin;
use App\Models\RegistrationRequest;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Contracts\Auth\Guard;
use Mockery;
use Tests\TestCase;
use Livewire\Component as LivewireComponent;

class RegistrationRequestResidenceMobileAccessTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/registration-request-residence-mobile-access.sqlite');

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

        $this->createLookupTables();
        $this->seedLookups();
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

    public function test_review_supervisor_can_edit_only_mobile_fields_during_final_approval(): void
    {
        $this->mockCurrentAdminWithRoles(['review-supervisor']);

        $record = new RegistrationRequest([
            'status' => RegistrationRequest::STATUS_PENDING_FINAL_APPROVAL,
        ]);

        $this->assertFalse($this->getField($record, 'residence_mobile_1_country_code')->isDisabled());
        $this->assertFalse($this->getField($record, 'residence_mobile_1')->isDisabled());
        $this->assertFalse($this->getField($record, 'residence_mobile_2_country_code')->isDisabled());
        $this->assertFalse($this->getField($record, 'residence_mobile_2')->isDisabled());

        $this->assertTrue($this->getField($record, 'residence_house_number')->isDisabled());
        $this->assertTrue($this->getField($record, 'residence_phone')->isDisabled());
        $this->assertTrue($this->getField($record, 'email')->isDisabled());
    }

    public function test_review_supervisor_cannot_edit_mobile_fields_before_final_approval(): void
    {
        $this->mockCurrentAdminWithRoles(['review-supervisor']);

        $record = new RegistrationRequest([
            'status' => RegistrationRequest::STATUS_PENDING_REVIEW,
        ]);

        $this->assertTrue($this->getField($record, 'residence_mobile_1_country_code')->isDisabled());
        $this->assertTrue($this->getField($record, 'residence_mobile_1')->isDisabled());
        $this->assertTrue($this->getField($record, 'residence_mobile_2_country_code')->isDisabled());
        $this->assertTrue($this->getField($record, 'residence_mobile_2')->isDisabled());
    }

    public function test_reviewer_still_can_edit_all_residence_fields_during_review(): void
    {
        $this->mockCurrentAdminWithRoles(['reviewer']);

        $record = new RegistrationRequest([
            'status' => RegistrationRequest::STATUS_PENDING_REVIEW,
        ]);

        $this->assertFalse($this->getField($record, 'residence_house_number')->isDisabled());
        $this->assertFalse($this->getField($record, 'residence_phone')->isDisabled());
        $this->assertFalse($this->getField($record, 'residence_mobile_1')->isDisabled());
        $this->assertFalse($this->getField($record, 'residence_mobile_2')->isDisabled());
        $this->assertFalse($this->getField($record, 'email')->isDisabled());
    }

    public function test_dashboard_mobile_number_fields_do_not_have_dashboard_specific_validation_rules(): void
    {
        $this->mockCurrentAdminWithRoles(['reviewer']);

        $record = new RegistrationRequest([
            'status' => RegistrationRequest::STATUS_PENDING_REVIEW,
        ]);

        $this->assertSame(['required'], $this->getField($record, 'residence_mobile_1')->getValidationRules());
        $this->assertSame(['nullable'], $this->getField($record, 'residence_mobile_2')->getValidationRules());
    }

    private function mockCurrentAdminWithRoles(array $roles): void
    {
        $admin = Mockery::mock(Admin::class);
        $admin->shouldReceive('hasRole')
            ->andReturnUsing(fn (string $role): bool => in_array($role, $roles, true));

        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturn($admin);

        Filament::shouldReceive('auth')->andReturn($guard);
    }

    private function getField(RegistrationRequest $record, string $statePath): Field
    {
        $schema = RegistrationRequestForm::configure(
            Schema::make($this->makeLivewireHost())->record($record)
        );

        $field = $schema->getComponentByStatePath($statePath, withHidden: true);

        $this->assertInstanceOf(Field::class, $field);

        return $field;
    }

    private function makeLivewireHost(): HasSchemas
    {
        return new class extends LivewireComponent implements HasSchemas {
            public function render(): string
            {
                return '';
            }

            public function makeFilamentTranslatableContentDriver(): ?\Filament\Support\Contracts\TranslatableContentDriver
            {
                return null;
            }

            public function getOldSchemaState(string $statePath): mixed
            {
                return null;
            }

            public function getSchemaComponent(
                string $key,
                bool $withHidden = false,
                array $skipComponentsChildContainersWhileSearching = []
            ): \Filament\Schemas\Components\Component|\Filament\Actions\Action|\Filament\Actions\ActionGroup|null {
                return null;
            }

            public function getSchema(string $name): ?Schema
            {
                return null;
            }

            public function currentlyValidatingSchema(?Schema $schema): void
            {
            }

            public function getDefaultTestingSchemaName(): ?string
            {
                return null;
            }
        };
    }

    private function createLookupTables(): void
    {
        DatabaseSchema::connection('sqlite')->create('nationalities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('code')->nullable();
            $table->json('name')->nullable();
            $table->timestamps();
        });

        DatabaseSchema::connection('sqlite')->create('religions', function (Blueprint $table): void {
            $table->id();
            $table->json('name')->nullable();
            $table->timestamps();
        });

        DatabaseSchema::connection('sqlite')->create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('code')->nullable();
            $table->json('name')->nullable();
            $table->timestamps();
        });

        DatabaseSchema::connection('sqlite')->create('medical_universities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('code')->nullable();
            $table->json('name')->nullable();
            $table->timestamps();
        });

        DatabaseSchema::connection('sqlite')->create('grades', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('code')->nullable();
            $table->json('name')->nullable();
            $table->timestamps();
        });

        DatabaseSchema::connection('sqlite')->create('languages', function (Blueprint $table): void {
            $table->id();
            $table->json('name')->nullable();
            $table->timestamps();
        });
    }

    private function seedLookups(): void
    {
        DB::table('nationalities')->insert([
            'id' => 1,
            'code' => 1,
            'name' => json_encode(['en' => 'Egyptian', 'ar' => 'مصري'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('religions')->insert([
            'id' => 1,
            'name' => json_encode(['en' => 'Muslim', 'ar' => 'مسلم'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('provinces')->insert([
            'id' => 1,
            'code' => 1,
            'name' => json_encode(['en' => 'Cairo', 'ar' => 'القاهرة'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('medical_universities')->insert([
            'id' => 1,
            'code' => 1,
            'name' => json_encode(['en' => 'Cairo University', 'ar' => 'جامعة القاهرة'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('grades')->insert([
            'id' => 1,
            'code' => 1,
            'name' => json_encode(['en' => 'Very Good', 'ar' => 'جيد جدًا'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('languages')->insert([
            [
                'id' => 1,
                'name' => json_encode(['en' => 'English', 'ar' => 'الإنجليزية'], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => json_encode(['en' => 'French', 'ar' => 'الفرنسية'], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
