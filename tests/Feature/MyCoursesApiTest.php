<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Models\PaymentMethod;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseBooking;
use Modules\Users\Models\User;
use Tests\TestCase;

class MyCoursesApiTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/my-courses.sqlite');

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
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_index_returns_authenticated_user_paid_courses_and_supports_filters(): void
    {
        $user = $this->seedAuthenticatedUser();
        $otherUser = $this->seedUser([
            'email' => 'other-courses@example.com',
            'phone' => '01000000001',
            'national_id' => '29901011230001',
            'reg_number' => '90001',
        ]);

        $this->seedPaymentMethods();

        $hybridCourse = $this->seedCourse([
            'title' => ['en' => 'Hybrid Nutrition', 'ar' => 'التغذية الهجينة'],
            'type' => 'hybrid',
            'price' => 4000,
            'start_date' => '2026-04-22',
            'end_date' => '2026-04-24',
        ]);
        $onlineCourse = $this->seedCourse([
            'title' => ['en' => 'Online Clinical Nutrition', 'ar' => 'التغذية العلاجية اونلاين'],
            'type' => 'online',
            'price' => 3000,
            'start_date' => '2026-05-10',
            'end_date' => '2026-05-12',
        ]);

        $hybridBooking = $this->seedPaidBooking($user, $hybridCourse, [
            'paid_at' => '2026-03-27 10:00:00',
            'amount' => 4000,
            'payment_method' => 'fawry',
            'gateway_reference' => 'HYB123',
        ]);
        $onlineBooking = $this->seedPaidBooking($user, $onlineCourse, [
            'paid_at' => '2026-03-26 10:00:00',
            'amount' => 3000,
            'payment_method' => 'instapay',
            'gateway_reference' => 'ONL123',
        ]);

        $pendingCourse = $this->seedCourse([
            'title' => ['en' => 'Pending Course', 'ar' => 'دورة معلقة'],
            'type' => 'attend',
        ]);
        CourseBooking::query()->create([
            'user_id' => $user->id,
            'course_id' => $pendingCourse->id,
            'price' => 2000,
            'total_amount' => 2000,
            'status' => 'pending_payment',
        ]);

        $otherCourse = $this->seedCourse([
            'title' => ['en' => 'Other User Course', 'ar' => 'دورة مستخدم آخر'],
            'type' => 'attend',
        ]);
        $this->seedPaidBooking($otherUser, $otherCourse, [
            'paid_at' => '2026-03-25 10:00:00',
            'amount' => 2500,
            'payment_method' => 'fawry',
            'gateway_reference' => 'OTH123',
        ]);

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->getJson('/api/v1/my-courses');

        $response->assertOk();
        $response->assertJsonPath('data.pagination.total', 2);
        $response->assertJsonPath('data.items.0.id', $hybridBooking->id);
        $response->assertJsonPath('data.items.0.title', 'Hybrid Nutrition');
        $response->assertJsonPath('data.items.0.type.key', 'hybrid');
        $response->assertJsonPath('data.items.1.id', $onlineBooking->id);
        $response->assertJsonPath('data.filters.types.0.key', 'all');

        $searchResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->getJson('/api/v1/my-courses?search=clinical');

        $searchResponse->assertOk();
        $searchResponse->assertJsonPath('data.pagination.total', 1);
        $searchResponse->assertJsonPath('data.items.0.id', $onlineBooking->id);

        $typeResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->getJson('/api/v1/my-courses?type=hybrid');

        $typeResponse->assertOk();
        $typeResponse->assertJsonPath('data.pagination.total', 1);
        $typeResponse->assertJsonPath('data.items.0.id', $hybridBooking->id);
    }

    public function test_show_returns_my_course_detail(): void
    {
        $user = $this->seedAuthenticatedUser();
        $this->seedPaymentMethods();

        $course = $this->seedCourse([
            'title' => ['en' => 'Annual Conference', 'ar' => 'المؤتمر السنوي'],
            'description' => ['en' => 'Detailed course description.', 'ar' => 'وصف تفصيلي للدورة.'],
            'type' => 'hybrid',
            'price' => 4000,
            'start_date' => '2026-04-22',
            'end_date' => '2026-04-24',
        ]);

        $booking = $this->seedPaidBooking($user, $course, [
            'paid_at' => '2026-03-27 10:00:00',
            'amount' => 4000,
            'payment_method' => 'fawry',
            'gateway_reference' => 'COURSE123',
            'merchant_ref_num' => 'EMSCB123',
        ]);

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->getJson("/api/v1/my-courses/{$booking->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $booking->id);
        $response->assertJsonPath('data.title', 'Annual Conference');
        $response->assertJsonPath('data.type.key', 'hybrid');
        $response->assertJsonPath('data.type.label', 'hybrid');
        $response->assertJsonPath('data.start_date', '2026-04-22');
        $response->assertJsonPath('data.end_date', '2026-04-24');
        $response->assertJsonPath('data.description', 'Detailed course description.');
        $response->assertJsonPath('data.price', '4000.00');
        $response->assertJsonPath('data.payment.amount', '4000.00');
        $response->assertJsonPath('data.payment.reference_number', 'COURSE123');
        $response->assertJsonPath('data.payment.payment_method.key', 'fawry');
        $response->assertJsonPath('data.payment.payment_method.label', 'Fawry');
    }

    private function seedAuthenticatedUser(array $attributes = []): User
    {
        $user = $this->seedUser($attributes);

        Sanctum::actingAs($user);

        return $user;
    }

    private function seedUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Course User',
            'phone' => '01012345678',
            'email' => 'courses@example.com',
            'password' => bcrypt('secret123'),
            'national_id' => '29901011234567',
            'reg_number' => '12345',
            'active' => true,
            'notification_enabled' => true,
        ], $attributes));
    }

    private function seedCourse(array $attributes = []): Course
    {
        return Course::query()->create(array_merge([
            'title' => ['en' => 'Course', 'ar' => 'دورة'],
            'description' => ['en' => 'Description', 'ar' => 'وصف'],
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-03',
            'price' => 2500,
            'available_count' => 10,
            'type' => 'attend',
            'is_active' => true,
            'is_featured' => false,
        ], $attributes));
    }

    private function seedPaidBooking(User $user, Course $course, array $attributes = []): CourseBooking
    {
        $booking = CourseBooking::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'price' => $attributes['amount'] ?? $course->price,
            'total_amount' => $attributes['amount'] ?? $course->price,
            'status' => 'paid_successfully',
            'paid_at' => $attributes['paid_at'] ?? now(),
        ]);

        $booking->order()->create([
            'user_id' => $user->id,
            'amount' => $attributes['amount'] ?? $course->price,
            'currency' => 'EGP',
            'status' => 'paid_successfully',
            'payment_method' => $attributes['payment_method'] ?? 'fawry',
            'merchant_ref_num' => $attributes['merchant_ref_num'] ?? null,
            'gateway_reference' => $attributes['gateway_reference'] ?? null,
            'paid_at' => $attributes['paid_at'] ?? now(),
        ]);

        return $booking->fresh(['course', 'order']);
    }

    private function seedPaymentMethods(): void
    {
        PaymentMethod::query()->create([
            'name' => ['en' => 'Fawry', 'ar' => 'فوري'],
            'key' => 'fawry',
            'is_active' => true,
        ]);

        PaymentMethod::query()->create([
            'name' => ['en' => 'InstaPay', 'ar' => 'انستاباي'],
            'key' => 'instapay',
            'is_active' => true,
        ]);
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

        Schema::connection('sqlite')->create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->string('key')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('courses', function (Blueprint $table): void {
            $table->id();
            $table->json('title');
            $table->json('description');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('available_count')->default(0);
            $table->string('type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();
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
    }
}
