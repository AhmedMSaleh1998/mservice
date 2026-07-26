<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ResetUnits\Pages\ViewResetUnit;
use App\Models\Admin;
use Tests\Support\RestUnitTestSchema;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Modules\Core\Models\Province;
use Modules\Services\Models\RestUnit;
use Modules\Services\Models\RestUnitBooking;
use Modules\Services\Models\RoomType;
use Modules\Users\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ResetUnitResourceTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/reset-unit-resource.sqlite');

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

        foreach (['ViewAny:RestUnit', 'View:RestUnit'] as $permission) {
            Permission::findOrCreate($permission, 'admin');
        }

        $admin->givePermissionTo(['ViewAny:RestUnit', 'View:RestUnit']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Filament::auth()->login($admin);
        $this->actingAs($admin, 'admin');
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

    public function test_view_page_shows_availability_and_guests_for_selected_period(): void
    {
        $province = $this->createProvince();
        $single = RoomType::query()->create(['name' => ['en' => 'Single room', 'ar' => 'غرفة فردية']]);
        $double = RoomType::query()->create(['name' => ['en' => 'Double room', 'ar' => 'غرفة مزدوجة']]);

        $unit = RestUnit::query()->create([
            'name' => ['en' => 'Al Ainy Rest House', 'ar' => 'استراحة القصر العيني'],
            'address' => ['en' => 'Cairo, Egypt', 'ar' => 'القاهرة، مصر'],
            'province_id' => $province->id,
            'type' => RestUnit::TYPE_ROOMS,
            'is_active' => true,
        ]);
        $singleRooms = collect(range(1, 3))->map(fn (int $i) => $unit->rooms()->create([
            'room_type_id' => $single->id, 'name' => "Single {$i}", 'price' => 4000, 'status' => 'in_service',
        ]));
        $doubleRooms = collect(range(1, 2))->map(fn (int $i) => $unit->rooms()->create([
            'room_type_id' => $double->id, 'name' => "Double {$i}", 'price' => 5000, 'status' => 'in_service',
        ]));

        $guestOne = $this->createUser(['name' => 'Guest One', 'phone' => '01011111111']);
        $guestTwo = $this->createUser(['name' => 'Guest Two', 'phone' => '01022222222']);
        $excludedGuest = $this->createUser(['name' => 'Excluded Guest', 'phone' => '01033333333']);

        RestUnitBooking::query()->create([
            'rest_unit_id' => $unit->id,
            'rest_unit_room_id' => $singleRooms[0]->id,
            'user_id' => $guestOne->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'status' => RestUnitBooking::STATUS_PAID_SUCCESSFULLY,
            'total_price' => 8000,
        ]);

        RestUnitBooking::query()->create([
            'rest_unit_id' => $unit->id,
            'rest_unit_room_id' => $doubleRooms[0]->id,
            'user_id' => $guestTwo->id,
            'start_date' => '2026-04-11',
            'end_date' => '2026-04-13',
            'status' => RestUnitBooking::STATUS_PENDING_PAYMENT,
            'total_price' => 9000,
        ]);

        RestUnitBooking::query()->create([
            'rest_unit_id' => $unit->id,
            'rest_unit_room_id' => $singleRooms[1]->id,
            'user_id' => $excludedGuest->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'status' => RestUnitBooking::STATUS_CANCELLED,
            'total_price' => 10000,
        ]);

        Livewire::withQueryParams([
            'from_date' => '2026-04-10',
            'to_date' => '2026-04-12',
        ])
            ->test(ViewResetUnit::class, ['record' => $unit->getKey()])
            ->assertSuccessful()
            ->assertSee('Availability for Selected Period')
            ->assertSee('2026-04-10')
            ->assertSee('2026-04-12')
            ->assertSee('Guest One')
            ->assertSee('Guest Two')
            ->assertDontSee('Excluded Guest')
            ->assertSee('Single room')
            ->assertSee('Double room')
            ->assertSee('Paid Successfully')
            ->assertSee('Pending Payment');
    }

    public function test_view_page_shows_empty_message_when_period_has_no_active_bookings(): void
    {
        $province = $this->createProvince();
        $single = RoomType::query()->create(['name' => ['en' => 'Single room', 'ar' => 'غرفة فردية']]);

        $unit = RestUnit::query()->create([
            'name' => ['en' => 'Al Ainy Rest House', 'ar' => 'استراحة القصر العيني'],
            'address' => ['en' => 'Cairo, Egypt', 'ar' => 'القاهرة، مصر'],
            'province_id' => $province->id,
            'type' => RestUnit::TYPE_ROOMS,
            'is_active' => true,
        ]);
        $room = $unit->rooms()->create(['room_type_id' => $single->id, 'name' => 'Single 1', 'price' => 4000, 'status' => 'in_service']);

        $guest = $this->createUser(['name' => 'Old Guest']);

        RestUnitBooking::query()->create([
            'rest_unit_id' => $unit->id,
            'rest_unit_room_id' => $room->id,
            'user_id' => $guest->id,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-03',
            'status' => RestUnitBooking::STATUS_PAID_SUCCESSFULLY,
            'total_price' => 8000,
        ]);

        Livewire::withQueryParams([
            'from_date' => '2026-04-10',
            'to_date' => '2026-04-12',
        ])
            ->test(ViewResetUnit::class, ['record' => $unit->getKey()])
            ->assertSuccessful()
            ->assertSee('No active bookings for the selected period.')
            ->assertDontSee('Old Guest');
    }

    public function test_view_page_shows_all_bookings_by_default_without_dates(): void
    {
        $province = $this->createProvince();
        $single = RoomType::query()->create(['name' => ['en' => 'Single room', 'ar' => 'غرفة فردية']]);

        $unit = RestUnit::query()->create([
            'name' => ['en' => 'Al Ainy Rest House', 'ar' => 'استراحة القصر العيني'],
            'address' => ['en' => 'Cairo, Egypt', 'ar' => 'القاهرة، مصر'],
            'province_id' => $province->id,
            'type' => RestUnit::TYPE_ROOMS,
            'is_active' => true,
        ]);
        $room = $unit->rooms()->create(['room_type_id' => $single->id, 'name' => 'Single 1', 'price' => 4000, 'status' => 'in_service']);

        $guest = $this->createUser(['name' => 'Future Guest']);
        RestUnitBooking::query()->create([
            'rest_unit_id' => $unit->id,
            'rest_unit_room_id' => $room->id,
            'user_id' => $guest->id,
            'start_date' => '2027-01-10',
            'end_date' => '2027-01-12',
            'status' => RestUnitBooking::STATUS_PAID_SUCCESSFULLY,
            'total_price' => 8000,
        ]);

        // No date query params → all bookings are listed.
        Livewire::test(ViewResetUnit::class, ['record' => $unit->getKey()])
            ->assertSuccessful()
            ->assertSee('Future Guest');
    }

    public function test_bed_maintenance_actions_are_logged(): void
    {
        $province = $this->createProvince();
        $unit = RestUnit::query()->create([
            'name' => ['en' => 'Beds', 'ar' => 'أسرّة'],
            'address' => ['en' => 'x', 'ar' => 'x'],
            'province_id' => $province->id,
            'type' => RestUnit::TYPE_BEDS,
            'price' => 100,
            'is_active' => true,
        ]);
        $bed = $unit->beds()->create(['label' => 'Bed 1', 'status' => 'in_service']);

        $bed->sendToMaintenance('AC broken');
        $bed->returnToService();

        $this->assertSame(2, $bed->maintenanceLogs()->count());
        $this->assertDatabaseHas('rest_unit_bed_maintenance_logs', [
            'rest_unit_bed_id' => $bed->id,
            'action' => \Modules\Services\Models\RestUnitBedMaintenanceLog::ACTION_TO_MAINTENANCE,
            'note' => 'AC broken',
        ]);
        $this->assertDatabaseHas('rest_unit_bed_maintenance_logs', [
            'rest_unit_bed_id' => $bed->id,
            'action' => \Modules\Services\Models\RestUnitBedMaintenanceLog::ACTION_RETURNED,
        ]);
    }

    private function createProvince(): Province
    {
        return Province::query()->create([
            'name' => ['en' => 'Cairo', 'ar' => 'القاهرة'],
            'shipping_cost' => 0,
        ]);
    }

    private function createUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Rest Unit Guest',
            'phone' => '01012345678',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'secret123',
            'national_id' => (string) fake()->unique()->numberBetween(10000000000000, 99999999999999),
            'reg_number' => (string) fake()->unique()->numberBetween(10000, 99999),
            'active' => true,
            'notification_enabled' => true,
        ], $attributes));
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

        Schema::connection('sqlite')->create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->timestamps();
        });

        $this->createRestUnitTables();

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

    private function createRestUnitTables(): void
    {
        RestUnitTestSchema::create('sqlite');
    }
}
