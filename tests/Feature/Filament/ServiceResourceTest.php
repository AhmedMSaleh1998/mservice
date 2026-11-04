<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceType;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ServiceResourceTest extends TestCase
{
    private string $databasePath;

    private ServiceType $serviceType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/service-resource.sqlite');

        if (! is_dir(dirname($this->databasePath))) {
            mkdir(dirname($this->databasePath), 0777, true);
        }

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        touch($this->databasePath);

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $this->databasePath);
        config()->set('cache.default', 'array');
        config()->set('permission.cache.store', 'array');
        config()->set('session.driver', 'array');

        DB::purge('sqlite');
        DB::disconnect('sqlite');
        Storage::fake('public');

        $this->createTables();

        app()->setLocale('en');
        Filament::setCurrentPanel(Filament::getPanel('manage'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Admin::query()->create([
            'name' => 'Manage Admin',
            'email' => 'manage-admin@example.com',
            'password' => 'password',
            'active' => true,
        ]);

        $permissions = ['ViewAny:Service', 'View:Service', 'Create:Service', 'Update:Service'];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'admin');
        }

        $admin->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Filament::auth()->login($admin);
        $this->actingAs($admin, 'admin');

        $this->serviceType = ServiceType::query()->create(['name' => ['en' => 'Administrative Services', 'ar' => 'خدمات إدارية']]);
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_create_derives_a_unique_key_from_the_english_title(): void
    {
        foreach (range(1, 2) as $attempt) {
            Livewire::test(CreateService::class)
                ->assertFormFieldDoesNotExist('key')
                ->fillForm([
                    'title' => ['en' => 'Pension Requests', 'ar' => 'المعاشات'],
                    'description' => ['en' => 'Pensions', 'ar' => 'المعاشات'],
                    'service_type_id' => $this->serviceType->id,
                ])
                ->call('create')
                ->assertHasNoFormErrors();
        }

        $this->assertSame(
            ['pension-requests', 'pension-requests-2'],
            Service::query()->orderBy('id')->pluck('key')->all(),
        );
    }

    public function test_editing_the_title_keeps_the_key(): void
    {
        $service = Service::query()->create([
            'key' => 'documents-procedures',
            'title' => ['en' => 'Documents and Procedures', 'ar' => 'المستندات والاجراءات'],
            'description' => ['en' => 'Documents and Procedures', 'ar' => 'المستندات والاجراءات'],
            'service_type_id' => $this->serviceType->id,
            'is_active' => true,
        ]);

        Livewire::test(EditService::class, ['record' => $service->getKey()])
            ->assertSuccessful()
            ->assertFormFieldDoesNotExist('key')
            ->fillForm([
                'title' => ['en' => 'Pensions', 'ar' => 'تقديم المعاشات'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $service->refresh();

        $this->assertSame('documents-procedures', $service->key);
        $this->assertSame('تقديم المعاشات', $service->getTranslation('title', 'ar'));
    }

    public function test_list_row_delete_action_soft_deletes_the_service(): void
    {
        Permission::findOrCreate('Delete:Service', 'admin');
        Filament::auth()->user()->givePermissionTo('Delete:Service');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $service = Service::query()->create([
            'key' => 'documents-procedures',
            'title' => ['en' => 'Documents and Procedures', 'ar' => 'المستندات والاجراءات'],
            'description' => ['en' => 'Documents and Procedures', 'ar' => 'المستندات والاجراءات'],
            'service_type_id' => $this->serviceType->id,
            'is_active' => true,
        ]);

        Livewire::test(ListServices::class)
            ->assertTableActionVisible('delete', $service)
            ->callTableAction('delete', $service);

        $this->assertSoftDeleted($service);
    }

    public function test_a_service_saved_earlier_with_a_non_slug_key_can_still_be_edited(): void
    {
        $service = Service::query()->create([
            'key' => 'المعاشات',
            'title' => ['en' => 'Pensions', 'ar' => 'تقديم المعاشات'],
            'description' => ['en' => 'Pensions', 'ar' => 'المستندات والاجراءات'],
            'service_type_id' => $this->serviceType->id,
            'is_active' => true,
        ]);

        Livewire::test(EditService::class, ['record' => $service->getKey()])
            ->fillForm(['is_featured' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($service->refresh()->is_featured);
    }

    private function createTables(): void
    {
        Schema::connection('sqlite')->create('admins', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::connection('sqlite')->create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::connection('sqlite')->create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::connection('sqlite')->create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::connection('sqlite')->create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

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
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('service_types', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('services', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->nullable()->unique();
            $table->json('title');
            $table->json('description');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('service_type_id')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('media', function (Blueprint $table): void {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->uuid('uuid')->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->text('manipulations');
            $table->text('custom_properties');
            $table->text('generated_conversions');
            $table->text('responsive_images');
            $table->unsignedInteger('order_column')->nullable();
            $table->nullableTimestamps();
        });
    }
}
