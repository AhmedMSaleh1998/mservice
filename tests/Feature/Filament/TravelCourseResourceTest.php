<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Filament\Resources\Courses\Pages\ViewCourse;
use App\Filament\Resources\Travels\Pages\ListTravels;
use App\Filament\Resources\Travels\Pages\ViewTravel;
use App\Filament\Resources\Travels\TravelResource;
use App\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Modules\Core\Models\Province;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseBooking;
use Modules\Travels\Models\Travel;
use Modules\Travels\Models\TravelBooking;
use Modules\Travels\Models\TravelBookingItem;
use Modules\Travels\Models\TravelCategory;
use Modules\Users\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TravelCourseResourceTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/travel-course-resource.sqlite');

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
        config()->set('checkout.reservation_timeout_minutes', 5);

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

        $permissions = [
            'ViewAny:Travel',
            'View:Travel',
            'ViewAny:Course',
            'View:Course',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'admin');
        }

        $admin->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Filament::auth()->login($admin);
        $this->actingAs($admin, 'admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_travel_resource_exposes_view_page_and_shows_capacity_and_travelers(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 12, 10, 0, 0, 'Africa/Cairo'));

        $travel = $this->createTravel();
        $vipCategory = TravelCategory::query()->create([
            'travel_id' => $travel->id,
            'code' => 'VIP',
            'name' => ['en' => 'VIP Bus', 'ar' => 'حافلة مميزة'],
            'price' => 1000,
            'capacity' => 5,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $standardCategory = TravelCategory::query()->create([
            'travel_id' => $travel->id,
            'code' => 'STD',
            'name' => ['en' => 'Standard Bus', 'ar' => 'حافلة عادية'],
            'price' => 800,
            'capacity' => 2,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $travelerOne = $this->createUser(['name' => 'Traveler One', 'phone' => '01011111111']);
        $travelerTwo = $this->createUser(['name' => 'Traveler Two', 'phone' => '01022222222']);
        $excludedTraveler = $this->createUser(['name' => 'Excluded Traveler', 'phone' => '01033333333']);

        $paidBooking = TravelBooking::query()->create([
            'travel_id' => $travel->id,
            'user_id' => $travelerOne->id,
            'status' => TravelBooking::STATUS_PAID_SUCCESSFULLY,
            'participants_count' => 2,
            'total_amount' => 2000,
            'paid_at' => now(),
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);
        TravelBookingItem::query()->create([
            'travel_booking_id' => $paidBooking->id,
            'travel_category_id' => $vipCategory->id,
            'category_code' => 'VIP',
            'category_name' => 'VIP Bus',
            'unit_price' => 1000,
            'quantity' => 2,
            'total_price' => 2000,
        ]);

        $pendingBooking = TravelBooking::query()->create([
            'travel_id' => $travel->id,
            'user_id' => $travelerTwo->id,
            'status' => TravelBooking::STATUS_PENDING_PAYMENT,
            'participants_count' => 1,
            'total_amount' => 800,
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinutes(2),
        ]);
        TravelBookingItem::query()->create([
            'travel_booking_id' => $pendingBooking->id,
            'travel_category_id' => $standardCategory->id,
            'category_code' => 'STD',
            'category_name' => 'Standard Bus',
            'unit_price' => 800,
            'quantity' => 1,
            'total_price' => 800,
        ]);

        $cancelledBooking = TravelBooking::query()->create([
            'travel_id' => $travel->id,
            'user_id' => $excludedTraveler->id,
            'status' => TravelBooking::STATUS_CANCELLED,
            'participants_count' => 1,
            'total_amount' => 800,
            'created_at' => now()->subMinutes(1),
            'updated_at' => now()->subMinutes(1),
        ]);
        TravelBookingItem::query()->create([
            'travel_booking_id' => $cancelledBooking->id,
            'travel_category_id' => $vipCategory->id,
            'category_code' => 'VIP',
            'category_name' => 'VIP Bus',
            'unit_price' => 1000,
            'quantity' => 1,
            'total_price' => 1000,
        ]);

        $this->assertArrayHasKey('view', TravelResource::getPages());

        Livewire::test(ListTravels::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$travel])
            ->assertTableActionExists('view', null, $travel);

        Livewire::test(ViewTravel::class, ['record' => $travel->getKey()])
            ->assertSuccessful()
            ->assertSee('Travel')
            ->assertSee('Capacity')
            ->assertSee('Travelers')
            ->assertSee('VIP Bus')
            ->assertSee('Standard Bus')
            ->assertSee('Traveler One')
            ->assertSee('Traveler Two')
            ->assertDontSee('Excluded Traveler')
            ->assertSee('Paid Successfully')
            ->assertSee('Pending Payment')
            ->assertSee('7')
            ->assertSee('3')
            ->assertSee('4');
    }

    public function test_course_resource_exposes_view_page_and_shows_bookings_and_attendees(): void
    {
        $course = $this->createCourse([
            'available_count' => 3,
            'type' => 'hybrid',
        ]);

        $userOne = $this->createUser(['name' => 'Course User One', 'phone' => '01044444444']);
        $userTwo = $this->createUser(['name' => 'Course User Two', 'phone' => '01055555555']);
        $expiredUser = $this->createUser(['name' => 'Expired User', 'phone' => '01066666666']);

        CourseBooking::query()->create([
            'course_id' => $course->id,
            'user_id' => $userOne->id,
            'price' => 4000,
            'total_amount' => 4000,
            'status' => 'paid_successfully',
            'paid_at' => '2026-04-10 10:00:00',
        ]);

        CourseBooking::query()->create([
            'course_id' => $course->id,
            'user_id' => $userTwo->id,
            'price' => 4000,
            'total_amount' => 4000,
            'status' => 'pending_payment',
        ]);

        CourseBooking::query()->create([
            'course_id' => $course->id,
            'user_id' => $expiredUser->id,
            'price' => 4000,
            'total_amount' => 4000,
            'status' => 'payment_expired',
        ]);

        $this->assertArrayHasKey('view', CourseResource::getPages());

        Livewire::test(ListCourses::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$course])
            ->assertTableActionExists('view', null, $course);

        Livewire::test(ViewCourse::class, ['record' => $course->getKey()])
            ->assertSuccessful()
            ->assertSee('Course')
            ->assertSee('Capacity')
            ->assertSee('Bookings')
            ->assertSee('Hybrid')
            ->assertSee('Course User One')
            ->assertSee('Course User Two')
            ->assertDontSee('Expired User')
            ->assertSee('Paid Successfully')
            ->assertSee('Pending Payment')
            ->assertSee('5')
            ->assertSee('2')
            ->assertSee('3');
    }

    private function createTravel(array $attributes = []): Travel
    {
        $province = Province::query()->create([
            'name' => ['en' => 'Cairo', 'ar' => 'القاهرة'],
            'shipping_cost' => 0,
        ]);

        return Travel::query()->create(array_merge([
            'title' => ['en' => 'Annual Trip', 'ar' => 'الرحلة السنوية'],
            'description' => ['en' => 'Travel summary test', 'ar' => 'اختبار ملخص الرحلة'],
            'location' => ['en' => 'Alexandria', 'ar' => 'الإسكندرية'],
            'province_id' => $province->id,
            'start_date' => '2026-04-20',
            'end_date' => '2026-04-24',
            'is_active' => true,
        ], $attributes));
    }

    private function createCourse(array $attributes = []): Course
    {
        return Course::query()->create(array_merge([
            'title' => ['en' => 'Surgery Basics', 'ar' => 'أساسيات الجراحة'],
            'description' => ['en' => 'Course summary test', 'ar' => 'اختبار ملخص الدورة'],
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-05',
            'price' => 4000,
            'available_count' => 5,
            'type' => 'attend',
            'is_active' => true,
            'is_featured' => false,
        ], $attributes));
    }

    private function createUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Dashboard User',
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

        Schema::connection('sqlite')->create('travels', function (Blueprint $table): void {
            $table->id();
            $table->json('title');
            $table->json('description')->nullable();
            $table->json('location')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->json('meeting_point_title')->nullable();
            $table->json('meeting_point_description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('travel_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('travel_id');
            $table->string('code');
            $table->json('name');
            $table->json('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('capacity')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('features')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('travel_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('travel_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status')->default('pending_payment');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->unsignedInteger('participants_count')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('sqlite')->create('travel_booking_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('travel_booking_id');
            $table->unsignedBigInteger('travel_category_id')->nullable();
            $table->string('category_code')->nullable();
            $table->string('category_name')->nullable();
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('courses', function (Blueprint $table): void {
            $table->id();
            $table->json('title');
            $table->json('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('available_count')->default(0);
            $table->string('type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('course_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('status')->default('pending_payment');
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
