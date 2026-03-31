<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Models\Order;
use Modules\Core\Models\PaymentMethod;
use Modules\Core\Models\Province;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseBooking;
use Modules\Memberships\Models\MembershipRequest;
use Modules\Services\Models\Service;
use Modules\Users\Models\User;
use Modules\Users\Models\UserAddress;
use Tests\TestCase;

class PaymentHistoryApiTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/payment-history.sqlite');

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
        Carbon::setTestNow();
        DB::disconnect('sqlite');

        if (isset($this->databasePath) && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();
    }

    public function test_index_returns_paid_payments_list_and_supports_search_and_date_filters(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 27, 18, 0, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $otherUser = $this->seedUser([
            'email' => 'other-payment@example.com',
            'phone' => '01000000001',
            'national_id' => '29901011230001',
            'reg_number' => '90001',
        ]);

        $this->seedPaymentMethods();
        $this->seedMembershipService();

        $address = $this->seedAddress($user, ['shipping_cost' => 250]);

        $membershipRequest = MembershipRequest::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Membership User',
            'specialty' => 'طبيب',
            'degree' => 'بكالوريوس طب وجراحة',
            'registration_number' => '12345',
            'delivery_method' => 'delivery',
            'status' => 'paid_successfully',
            'printing_cost' => 4500,
            'delivery_cost' => 250,
            'subscription_cost' => 0,
            'total_amount' => 4750,
            'user_address_id' => $address->id,
            'created_at' => '2026-03-27 09:00:00',
            'updated_at' => '2026-03-27 09:00:00',
        ]);
        $membershipOrder = $membershipRequest->order()->create([
            'user_id' => $user->id,
            'amount' => 4750,
            'currency' => 'EGP',
            'status' => 'paid_successfully',
            'payment_method' => 'fawry',
            'merchant_ref_num' => 'EMSMID1AAA',
            'gateway_reference' => '123456',
            'paid_at' => '2026-03-27 10:00:00',
            'created_at' => '2026-03-27 09:00:00',
            'updated_at' => '2026-03-27 10:00:00',
        ]);

        $course = Course::query()->create([
            'title' => ['en' => 'Advanced Course', 'ar' => 'دورة متقدمة'],
            'description' => ['en' => 'Course desc', 'ar' => 'وصف الدورة'],
            'price' => 3000,
            'available_count' => 10,
            'type' => 'online',
            'is_active' => true,
            'is_featured' => false,
        ]);
        $courseBooking = CourseBooking::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'price' => 3000,
            'total_amount' => 3000,
            'status' => 'paid_successfully',
            'paid_at' => '2026-03-26 11:00:00',
        ]);
        $courseOrder = $courseBooking->order()->create([
            'user_id' => $user->id,
            'amount' => 3000,
            'currency' => 'EGP',
            'status' => 'paid_successfully',
            'payment_method' => 'instapay',
            'merchant_ref_num' => 'EMSCB2BBB',
            'gateway_reference' => '654321',
            'paid_at' => '2026-03-26 11:00:00',
        ]);

        $otherAddress = $this->seedAddress($otherUser);
        $otherMembership = MembershipRequest::query()->create([
            'user_id' => $otherUser->id,
            'full_name' => 'Other User',
            'specialty' => 'طبيب',
            'degree' => 'بكالوريوس طب وجراحة',
            'registration_number' => '67890',
            'delivery_method' => 'delivery',
            'status' => 'paid_successfully',
            'printing_cost' => 4000,
            'delivery_cost' => 0,
            'subscription_cost' => 0,
            'total_amount' => 4000,
            'user_address_id' => $otherAddress->id,
        ]);
        $otherMembership->order()->create([
            'user_id' => $otherUser->id,
            'amount' => 4000,
            'currency' => 'EGP',
            'status' => 'paid_successfully',
            'payment_method' => 'fawry',
            'paid_at' => '2026-03-25 10:00:00',
        ]);

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->getJson('/api/v1/payments');

        $response->assertOk();
        $response->assertJsonPath('data.items.0.id', $membershipOrder->id);
        $response->assertJsonPath('data.items.0.title', 'Membership ID');
        $response->assertJsonPath('data.items.0.reference_number', '123456');
        $response->assertJsonPath('data.items.0.amount', '4750.00');
        $response->assertJsonPath('data.items.0.payment_method.label', 'Fawry');
        $response->assertJsonPath('data.items.1.id', $courseOrder->id);
        $response->assertJsonPath('data.items.1.title', 'Advanced Course');
        $response->assertJsonPath('data.items.1.payment_method.label', 'InstaPay');
        $response->assertJsonPath('data.pagination.total', 2);

        $searchResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->getJson('/api/v1/payments?search=advanced');

        $searchResponse->assertOk();
        $searchResponse->assertJsonPath('data.pagination.total', 1);
        $searchResponse->assertJsonPath('data.items.0.id', $courseOrder->id);

        $dateResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->getJson('/api/v1/payments?date=2026-03-27');

        $dateResponse->assertOk();
        $dateResponse->assertJsonPath('data.pagination.total', 1);
        $dateResponse->assertJsonPath('data.items.0.id', $membershipOrder->id);
    }

    public function test_show_returns_payment_detail_for_paid_membership_order(): void
    {
        $user = $this->seedAuthenticatedUser();
        $this->seedPaymentMethods();
        $this->seedMembershipService();

        $address = $this->seedAddress($user, ['shipping_cost' => 250]);

        $membershipRequest = MembershipRequest::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Membership User',
            'specialty' => 'طبيب',
            'degree' => 'بكالوريوس طب وجراحة',
            'registration_number' => '12345',
            'delivery_method' => 'delivery',
            'status' => 'paid_successfully',
            'printing_cost' => 4500,
            'delivery_cost' => 250,
            'subscription_cost' => 0,
            'total_amount' => 4750,
            'user_address_id' => $address->id,
        ]);
        $order = $membershipRequest->order()->create([
            'user_id' => $user->id,
            'amount' => 4750,
            'currency' => 'EGP',
            'status' => 'paid_successfully',
            'payment_method' => 'fawry',
            'merchant_ref_num' => 'EMSMID1XYZ',
            'gateway_reference' => '123456',
            'paid_at' => '2026-03-27 10:00:00',
        ]);

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->getJson("/api/v1/payments/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $order->id);
        $response->assertJsonPath('data.title', 'Membership ID');
        $response->assertJsonPath('data.reference_number', '123456');
        $response->assertJsonPath('data.amount', '4750.00');
        $response->assertJsonPath('data.payment_method.key', 'fawry');
        $response->assertJsonPath('data.payment_method.label', 'Fawry');
        $response->assertJsonPath('data.request.type', 'membership_request');
        $response->assertJsonPath('data.request.id', $membershipRequest->id);
        $response->assertJsonPath('data.items.0.label', 'Membership printing');
        $response->assertJsonPath('data.items.0.amount', '4500.00');
        $response->assertJsonPath('data.items.1.label', 'Shipping fees');
        $response->assertJsonPath('data.items.1.amount', '250.00');
        $response->assertJsonPath('data.total', '4750.00');
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
            'name' => 'Payment User',
            'phone' => '01012345678',
            'email' => 'payments@example.com',
            'password' => bcrypt('secret123'),
            'national_id' => '29901011234567',
            'reg_number' => '12345',
            'active' => true,
            'notification_enabled' => true,
        ], $attributes));
    }

    private function seedMembershipService(): Service
    {
        return Service::query()->create([
            'title' => ['en' => 'Membership ID', 'ar' => 'استخراج كارنية عضوية'],
            'description' => ['en' => 'Membership ID', 'ar' => 'استخراج كارنية عضوية'],
            'key' => 'membership-id',
            'price' => 0,
            'is_active' => true,
            'is_featured' => false,
        ]);
    }

    private function seedAddress(User $user, array $provinceAttributes = []): UserAddress
    {
        $province = Province::query()->create(array_merge([
            'name' => ['en' => 'Cairo', 'ar' => 'القاهرة'],
            'shipping_cost' => 0,
        ], $provinceAttributes));

        return UserAddress::query()->create([
            'user_id' => $user->id,
            'province_id' => $province->id,
            'district' => 'Nasr City',
            'street' => 'Street 1',
            'lat' => 30.0444,
            'lng' => 31.2357,
            'phone' => '01012345678',
            'unit_number' => '12',
            'address_name' => 'Home',
        ]);
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

        Schema::connection('sqlite')->create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->string('key')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('services', function (Blueprint $table): void {
            $table->id();
            $table->json('title');
            $table->json('description');
            $table->string('key')->nullable();
            $table->unsignedBigInteger('service_type_id')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('sqlite')->create('provinces', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('user_addresses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('province_id');
            $table->string('district');
            $table->string('street');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('phone')->nullable();
            $table->string('unit_number');
            $table->string('address_name');
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('membership_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('full_name');
            $table->string('specialty');
            $table->string('degree');
            $table->string('registration_number');
            $table->string('delivery_method')->default('delivery');
            $table->text('address')->nullable();
            $table->string('status')->default('pending_payment');
            $table->string('delivery_status')->nullable();
            $table->decimal('printing_cost', 10, 2)->default(0);
            $table->decimal('delivery_cost', 10, 2)->default(0);
            $table->decimal('subscription_cost', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->unsignedBigInteger('user_address_id')->nullable();
            $table->string('payment_method')->nullable();
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
