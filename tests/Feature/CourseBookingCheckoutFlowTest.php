<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Models\Order;
use Modules\Core\Models\PaymentMethod;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseBooking;
use Modules\Users\Models\User;
use Tests\TestCase;

class CourseBookingCheckoutFlowTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/course-booking-checkout-flow.sqlite');

        if (! is_dir(dirname($this->databasePath))) {
            mkdir(dirname($this->databasePath), 0777, true);
        }

        if (! file_exists($this->databasePath)) {
            touch($this->databasePath);
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $this->databasePath);
        config()->set('checkout.reservation_timeout_minutes', 5);

        DB::purge('sqlite');
        DB::disconnect('sqlite');

        $this->createTables();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_store_creates_course_booking_and_decrements_available_count(): void
    {
        $user = $this->seedAuthenticatedUser();
        $course = $this->seedCourse();
        $this->seedPaymentMethods();

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->post("/api/v1/courses/{$course->id}/booking");

        $response->assertCreated();
        $response->assertJsonPath('message', 'Order created successfully.');
        $response->assertJsonPath('data.order.request.type', 'course_booking');
        $response->assertJsonPath('data.order.request.status', 'pending_payment');
        $response->assertJsonPath('data.order.items.0.label', 'Course booking');
        $response->assertJsonPath('data.order.items.0.amount', '4000.00');
        $response->assertJsonPath('data.order.total', '4000.00');
        $response->assertJsonPath('data.payment_methods.0.key', 'fawry');

        $bookingId = (int) $response->json('data.order.request.id');
        $orderId = (int) $response->json('data.order.id');

        $this->assertDatabaseHas('course_bookings', [
            'id' => $bookingId,
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'pending_payment',
            'total_amount' => 4000,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'orderable_type' => CourseBooking::class,
            'orderable_id' => $bookingId,
            'amount' => 4000,
        ]);
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'available_count' => 1,
        ]);
    }

    public function test_store_blocks_booking_when_course_is_fully_booked(): void
    {
        $this->seedAuthenticatedUser();
        $course = $this->seedCourse([
            'available_count' => 0,
        ]);
        $this->seedPaymentMethods();

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->post("/api/v1/courses/{$course->id}/booking");

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'This course is fully booked.');
    }

    public function test_pay_and_confirm_mark_course_booking_paid(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 17, 10, 0, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $course = $this->seedCourse([
            'available_count' => 1,
        ]);
        $this->seedPaymentMethods();
        config()->set('services.fawry.enabled', false);

        $course->decrement('available_count');

        $courseBooking = CourseBooking::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'price' => 4000,
            'total_amount' => 4000,
            'status' => 'pending_payment',
        ]);
        $order = $this->createOrderForCourseBooking($courseBooking);

        $checkoutResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/orders/{$order->id}/pay", [
                'payment_method' => 'fawry',
            ]);

        $checkoutResponse->assertOk();
        $checkoutResponse->assertJsonPath('data.order.request.status', 'pending_payment');
        $checkoutResponse->assertJsonPath('data.checkout.mode', 'mock');
        $checkoutResponse->assertJsonPath('data.order.payment_method', 'fawry');

        $confirmResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/orders/{$order->id}/confirm-payment");

        $confirmResponse->assertOk();
        $confirmResponse->assertJsonPath('data.order.request.status', 'paid_successfully');
        $confirmResponse->assertJsonPath('data.order.status', 'paid_successfully');

        $this->assertDatabaseHas('course_bookings', [
            'id' => $courseBooking->id,
            'status' => 'paid_successfully',
            'paid_at' => '2026-03-17 10:00:00',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid_successfully',
            'gateway_status' => 'PAID',
        ]);
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'available_count' => 0,
        ]);
    }

    public function test_pay_rejects_expired_course_booking_and_releases_seat(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 17, 10, 0, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $course = $this->seedCourse([
            'available_count' => 0,
        ]);
        $this->seedPaymentMethods();

        $courseBooking = CourseBooking::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'price' => 4000,
            'total_amount' => 4000,
            'status' => 'pending_payment',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $order = $this->createOrderForCourseBooking($courseBooking);

        Carbon::setTestNow(Carbon::now()->addMinutes(6));

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/orders/{$order->id}/pay", [
                'payment_method' => 'fawry',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Course booking reservation has expired.');

        $this->assertDatabaseHas('course_bookings', [
            'id' => $courseBooking->id,
            'status' => 'payment_expired',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'payment_expired',
            'gateway_status' => 'EXPIRED',
            'checkout_url' => null,
        ]);
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'available_count' => 1,
        ]);
    }

    public function test_release_expired_course_bookings_command_restores_available_count(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 17, 10, 0, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $course = $this->seedCourse([
            'available_count' => 0,
        ]);

        $courseBooking = CourseBooking::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'price' => 4000,
            'total_amount' => 4000,
            'status' => 'pending_payment',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $order = $this->createOrderForCourseBooking($courseBooking);

        Carbon::setTestNow(Carbon::now()->addMinutes(6));

        Artisan::call('courses:release-expired-bookings');

        $this->assertDatabaseHas('course_bookings', [
            'id' => $courseBooking->id,
            'status' => 'payment_expired',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'payment_expired',
            'gateway_status' => 'EXPIRED',
        ]);
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'available_count' => 1,
        ]);
    }

    public function test_fawry_return_rejects_late_paid_course_booking_and_restores_seat(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 17, 10, 0, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $course = $this->seedCourse([
            'available_count' => 0,
        ]);
        $this->seedPaymentMethods();
        $this->configureFawry();
        config()->set('services.fawry.frontend_return_url', 'https://frontend.example.test/payment-result');

        $courseBooking = CourseBooking::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'price' => 4000,
            'total_amount' => 4000,
            'status' => 'pending_payment',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $order = $this->createOrderForCourseBooking($courseBooking, [
            'status' => 'checkout_pending',
            'payment_method' => 'fawry',
            'provider' => 'fawry',
            'merchant_ref_num' => 'CB-LATE-1',
            'gateway_status' => 'NEW',
            'checkout_url' => 'https://atfawry.fawrystaging.com/checkout/session-cb',
        ]);

        $latePaymentTime = Carbon::now()->addMinutes(16)->getTimestampMs();
        Carbon::setTestNow(Carbon::now()->addMinutes(16));

        $payload = [
            'statusCode' => 200,
            'statusDescription' => 'Operation done successfully',
            'referenceNumber' => '443322',
            'merchantRefNumber' => 'CB-LATE-1',
            'paymentAmount' => '4000.00',
            'orderAmount' => '4000.00',
            'orderStatus' => 'PAID',
            'paymentMethod' => 'PayAtFawry',
            'paymentTime' => $latePaymentTime,
            'fawryFees' => '0.00',
            'shippingFees' => '0.00',
            'authNumber' => '',
            'customerMail' => $user->email,
            'customerMobile' => $user->phone,
        ];
        $payload['signature'] = $this->buildFawryReturnSignature($payload);

        $response = $this->get('/api/v1/payments/fawry/orders/return?' . http_build_query($payload));

        $response->assertRedirect('https://frontend.example.test/payment-result?order_id=' . $order->id . '&course_booking_id=' . $courseBooking->id . '&course_id=' . $course->id . '&merchant_ref_num=CB-LATE-1&success=0&status_code=200&status_description=Operation+done+successfully&order_status=PAID&reference_number=443322');

        $this->assertDatabaseHas('course_bookings', [
            'id' => $courseBooking->id,
            'status' => 'payment_expired',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'payment_expired',
            'gateway_status' => 'EXPIRED',
            'gateway_reference' => '443322',
            'checkout_url' => null,
        ]);
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'available_count' => 1,
        ]);
    }

    private function seedAuthenticatedUser(): User
    {
        $user = User::query()->create([
            'name' => 'Course User',
            'phone' => '01012345678',
            'email' => 'course@example.com',
            'password' => bcrypt('secret123'),
            'national_id' => '29901011234567',
            'reg_number' => '12345',
            'active' => true,
            'notification_enabled' => true,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    private function seedCourse(array $attributes = []): Course
    {
        return Course::query()->create(array_merge([
            'title' => ['en' => 'Medical Course', 'ar' => 'دورة طبية'],
            'description' => ['en' => 'Course description', 'ar' => 'وصف الدورة'],
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-02',
            'price' => 4000,
            'available_count' => 2,
            'type' => 'attend',
            'is_active' => true,
            'is_featured' => false,
        ], $attributes));
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

    private function configureFawry(): void
    {
        config()->set('services.fawry.enabled', true);
        config()->set('services.fawry.base_url', 'https://atfawry.fawrystaging.com');
        config()->set('services.fawry.merchant_code', 'TESTMERCHANT');
        config()->set('services.fawry.secure_key', 'TESTSECUREKEY');
        config()->set('services.fawry.currency_code', 'EGP');
        config()->set('services.fawry.merchant_ref_prefix', 'EMS');
        config()->set('services.fawry.payment_method', 'PayAtFawry');
        config()->set('services.fawry.payment_expiry_minutes', 5);
        config()->set('services.fawry.payment_expiry_hours', 1);
        config()->set('services.fawry.return_url', 'https://api.example.test/api/v1/payments/fawry/orders/return');
        config()->set('services.fawry.frontend_return_url', null);
        config()->set('services.fawry.webhook_url', 'https://api.example.test/api/v1/payments/fawry/orders/notification');
    }

    private function buildFawryReturnSignature(array $payload): string
    {
        return hash('sha256',
            $payload['referenceNumber']
            . $payload['merchantRefNumber']
            . $this->formatAmount($payload['paymentAmount'])
            . $this->formatAmount($payload['orderAmount'])
            . $payload['orderStatus']
            . $payload['paymentMethod']
            . $this->formatAmount($payload['fawryFees'])
            . $this->formatAmount($payload['shippingFees'])
            . $payload['authNumber']
            . $payload['customerMail']
            . $payload['customerMobile']
            . 'TESTSECUREKEY'
        );
    }

    private function formatAmount(string|int|float $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function createOrderForCourseBooking(CourseBooking $courseBooking, array $attributes = []): Order
    {
        return $courseBooking->order()->create(array_merge([
            'user_id' => $courseBooking->user_id,
            'amount' => $courseBooking->total_amount,
            'currency' => 'EGP',
            'status' => 'pending_payment',
        ], $attributes));
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
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('courses', function (Blueprint $table): void {
            $table->id();
            $table->json('title');
            $table->json('description');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->unsignedInteger('available_count')->default(0);
            $table->string('type')->default('attend');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('course_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->decimal('price', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->string('status')->default('pending_payment');
            $table->timestamp('paid_at')->nullable();
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
            $table->json('payload')->nullable();
            $table->timestamp('payment_started_at')->nullable();
            $table->timestamp('payment_last_synced_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }
}
