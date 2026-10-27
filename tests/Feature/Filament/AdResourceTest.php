<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AdRequests\Pages\EditAdRequest;
use App\Filament\Resources\AdRequests\Pages\ListAdRequests;
use App\Filament\Resources\AdRequests\Pages\ViewAdRequest;
use App\Filament\Resources\Ads\Pages\ListAds;
use App\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Ads\Enums\AdDisplayStateEnum;
use Modules\Ads\Enums\AdRequestStatusEnum;
use Modules\Ads\Models\AdRequest;
use Modules\Ads\Models\AdSpace;
use Modules\Services\Models\Service;
use Modules\Users\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdResourceTest extends TestCase
{
    private string $databasePath;

    private AdSpace $adSpace;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/ad-resource.sqlite');

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

        $permissions = ['ViewAny:AdRequest', 'View:AdRequest', 'Update:AdRequest'];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'admin');
        }

        $admin->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Filament::auth()->login($admin);
        $this->actingAs($admin, 'admin');

        $service = Service::query()->create([
            'title' => ['en' => 'Homepage Banner', 'ar' => 'بانر الصفحة الرئيسية'],
            'description' => ['en' => 'Banner', 'ar' => 'بانر'],
            'is_active' => true,
        ]);

        $this->adSpace = AdSpace::query()->create([
            'service_id' => $service->id,
            'price_per_month' => 1000,
            'is_available' => false,
        ]);

        $this->user = User::query()->create([
            'name' => 'Doctor Ad',
            'password' => 'password',
            'reg_number' => '213931',
        ]);
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

    public function test_ads_list_shows_only_approved_ads_with_their_state(): void
    {
        $running = $this->createAd(AdRequestStatusEnum::APPROVED, now()->subDay(), now()->addMonth());
        // Approved but past its period, not yet swept to completed.
        $lapsed = $this->createAd(AdRequestStatusEnum::APPROVED, now()->subMonths(2), now()->subDay());
        $completed = $this->createAd(AdRequestStatusEnum::COMPLETED, now()->subMonths(2), now()->subMonth());
        $awaitingReview = $this->createAd(AdRequestStatusEnum::PAID_SUCCESSFULLY);
        $rejected = $this->createAd(AdRequestStatusEnum::REJECTED);

        Livewire::test(ListAds::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$running, $lapsed, $completed])
            ->assertCanNotSeeTableRecords([$awaitingReview, $rejected])
            ->assertTableColumnStateSet('display_state', AdDisplayStateEnum::RUNNING->label(), $running)
            ->assertTableColumnStateSet('display_state', AdDisplayStateEnum::ENDED->label(), $lapsed)
            ->assertTableColumnStateSet('display_state', AdDisplayStateEnum::ENDED->label(), $completed)
            ->filterTable('display_state', AdDisplayStateEnum::ENDED->value)
            ->assertCanSeeTableRecords([$lapsed, $completed])
            ->assertCanNotSeeTableRecords([$running])
            ->filterTable('display_state', AdDisplayStateEnum::RUNNING->value)
            ->assertCanSeeTableRecords([$running])
            ->assertCanNotSeeTableRecords([$lapsed, $completed]);
    }

    public function test_ad_requests_list_opens_on_paid_requests_that_were_not_approved(): void
    {
        $paid = $this->createAd(AdRequestStatusEnum::PAID_SUCCESSFULLY);
        $rejected = $this->createAd(AdRequestStatusEnum::REJECTED);
        $approved = $this->createAd(AdRequestStatusEnum::APPROVED, now()->subDay(), now()->addMonth());
        $expired = $this->createAd(AdRequestStatusEnum::PAYMENT_EXPIRED);
        $pending = $this->createAd(AdRequestStatusEnum::PENDING_PAYMENT);

        Livewire::test(ListAdRequests::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$paid, $rejected])
            ->assertCanNotSeeTableRecords([$approved, $expired, $pending])
            ->filterTable('status', [AdRequestStatusEnum::PAYMENT_EXPIRED->value])
            ->assertCanSeeTableRecords([$expired])
            ->assertCanNotSeeTableRecords([$paid, $approved, $rejected, $pending])
            ->removeTableFilters()
            ->assertCanSeeTableRecords([$paid, $approved, $rejected, $expired, $pending]);
    }

    public function test_toggle_hides_and_shows_a_running_ad(): void
    {
        $running = $this->createAd(AdRequestStatusEnum::APPROVED, now()->subDay(), now()->addMonth());

        Livewire::test(ListAds::class)
            ->call('updateTableColumnState', 'is_visible', (string) $running->getKey(), false);

        $this->assertFalse($running->refresh()->is_visible);

        Livewire::test(ViewAdRequest::class, ['record' => $running->getKey()])
            ->assertSuccessful()
            ->callAction('toggleVisibility');

        $this->assertTrue($running->refresh()->is_visible);
    }

    public function test_edit_page_deactivates_ad_and_replaces_design_image(): void
    {
        $running = $this->createAd(AdRequestStatusEnum::APPROVED, now()->subDay(), now()->addMonth());
        $oldMedia = $running->addMedia(UploadedFile::fake()->image('old.png'))->toMediaCollection('design_image');

        Livewire::test(EditAdRequest::class, ['record' => $running->getKey()])
            ->assertSuccessful()
            ->assertSee('Doctor Ad')
            ->assertSee('213931')
            ->assertSee('Homepage Banner')
            ->fillForm([
                'is_visible' => false,
                'design_image_media' => [UploadedFile::fake()->image('new.png')],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $running->refresh();

        $this->assertFalse($running->is_visible);
        $this->assertSame(AdRequestStatusEnum::APPROVED, $running->statusEnum());
        $this->assertCount(1, $running->getMedia('design_image'));
        $this->assertNotSame($oldMedia->id, $running->getFirstMedia('design_image')->id);
    }

    private function createAd(AdRequestStatusEnum $status, $startsAt = null, $endsAt = null): AdRequest
    {
        return AdRequest::query()->create([
            'user_id' => $this->user->id,
            'ad_space_id' => $this->adSpace->id,
            'duration_months' => 1,
            'price_per_month' => 1000,
            'total_amount' => 1000,
            'ad_text' => 'Clinic opening',
            'status' => $status->value,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
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

        Schema::connection('sqlite')->create('services', function (Blueprint $table): void {
            $table->id();
            $table->json('title');
            $table->json('description');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('ad_spaces', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->unsignedInteger('max_characters')->nullable();
            $table->unsignedInteger('min_duration_months')->default(1);
            $table->decimal('price_per_month', 10, 2)->default(0);
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('ad_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('ad_space_id');
            $table->unsignedInteger('duration_months');
            $table->decimal('price_per_month', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->text('ad_text')->nullable();
            $table->string('design_image')->nullable();
            $table->string('status')->default('pending_payment');
            $table->boolean('is_visible')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->string('key')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('orderable_type');
            $table->unsignedBigInteger('orderable_id');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->string('status')->default('pending_payment');
            $table->string('payment_method')->nullable();
            $table->string('provider')->nullable();
            $table->string('merchant_ref_num')->nullable();
            $table->string('gateway_reference')->nullable();
            $table->string('gateway_status')->nullable();
            $table->text('checkout_url')->nullable();
            $table->text('payload')->nullable();
            $table->timestamp('payment_started_at')->nullable();
            $table->timestamp('payment_last_synced_at')->nullable();
            $table->timestamp('paid_at')->nullable();
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
