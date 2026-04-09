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
use Modules\Core\Models\Province;
use Modules\Core\Services\SubscriptionChargeService;
use Modules\Travels\Models\Travel;
use Modules\Travels\Models\TravelBooking;
use Modules\Travels\Models\TravelCategory;
use Modules\Users\Models\User;
use Tests\TestCase;

class TravelBookingFlowTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/travel-booking-flow.sqlite');

        if (! is_dir(dirname($this->databasePath))) {
            mkdir(dirname($this->databasePath), 0777, true);
        }

        if (! file_exists($this->databasePath)) {
            touch($this->databasePath);
        }

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $this->databasePath);
        config()->set('checkout.reservation_timeout_minutes', 5);
        config()->set('services.oracle.payment_sync_enabled', false);
        config()->set('services.fawry.enabled', false);

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

    public function test_index_supports_search_and_hides_past_travels(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 0, 0, 'Africa/Cairo'));

        $this->seedAuthenticatedUser();
        $province = $this->seedProvince();
        $alexTravel = $this->seedTravel([
            'title' => ['en' => 'Alexandria Medical Trip', 'ar' => 'رحلة الإسكندرية الطبية'],
            'location' => ['en' => 'Alexandria', 'ar' => 'الإسكندرية'],
            'province_id' => $province->id,
            'start_date' => '2026-08-22',
            'end_date' => '2026-08-22',
        ]);
        $this->seedCategory($alexTravel, ['code' => 'A', 'name' => ['en' => 'Plan A', 'ar' => 'خطة A'], 'price' => 6000, 'capacity' => 2]);
        $this->seedCategory($alexTravel, ['code' => 'C', 'name' => ['en' => 'Plan C', 'ar' => 'خطة C'], 'price' => 4000, 'capacity' => 1]);

        $beirutTravel = $this->seedTravel([
            'title' => ['en' => 'Lebanon Congress Travel', 'ar' => 'رحلة لبنان للمؤتمر'],
            'location' => ['en' => 'Beirut', 'ar' => 'بيروت'],
            'province_id' => $province->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-15',
        ]);
        $this->seedCategory($beirutTravel, ['code' => 'A', 'name' => ['en' => 'Plan A', 'ar' => 'خطة A'], 'price' => 9000, 'capacity' => 3]);

        $pastTravel = $this->seedTravel([
            'title' => ['en' => 'Expired Travel', 'ar' => 'رحلة منتهية'],
            'location' => ['en' => 'Cairo', 'ar' => 'القاهرة'],
            'province_id' => $province->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-02',
        ]);
        $this->seedCategory($pastTravel, ['code' => 'A', 'name' => ['en' => 'Plan A', 'ar' => 'خطة A'], 'price' => 3000, 'capacity' => 2]);

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->getJson('/api/v1/travels?search=alexandria');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $alexTravel->id);
        $response->assertJsonPath('data.0.title', 'Alexandria Medical Trip');
        $response->assertJsonPath('data.0.province', $province->name);
        $response->assertJsonPath('data.0.starting_price', '4000.00');
        $response->assertJsonPath('data.0.available_slots', 3);
        $response->assertJsonMissingPath('data.0.location');
        $response->assertJsonMissingPath('data.0.currency');
        $response->assertJsonMissingPath('meta');
        $this->assertStringEndsWith('/api/v1/travels?page=1', (string) $response->json('links.first'));
    }

    public function test_travels_endpoints_require_authentication(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 0, 0, 'Africa/Cairo'));

        $travel = $this->seedTravel();

        $this->getJson('/api/v1/travels')->assertUnauthorized();
        $this->getJson("/api/v1/travels/{$travel->id}")->assertUnauthorized();
    }

    public function test_show_returns_category_availability_for_travel_details(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 0, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $province = $this->seedProvince();
        $travel = $this->seedTravel([
            'province_id' => $province->id,
            'start_date' => '2026-08-22',
            'end_date' => '2026-08-22',
        ]);
        $categoryA = $this->seedCategory($travel, ['code' => 'A', 'name' => ['en' => 'Plan A', 'ar' => 'خطة A'], 'price' => 6000, 'capacity' => 2]);
        $categoryB = $this->seedCategory($travel, ['code' => 'B', 'name' => ['en' => 'Plan B', 'ar' => 'خطة B'], 'price' => 6000, 'capacity' => 3]);
        $categoryC = $this->seedCategory($travel, ['code' => 'C', 'name' => ['en' => 'Plan C', 'ar' => 'خطة C'], 'price' => 4000, 'capacity' => 1]);

        $booking = TravelBooking::query()->create([
            'travel_id' => $travel->id,
            'user_id' => $user->id,
            'status' => TravelBooking::STATUS_PAID_SUCCESSFULLY,
            'total_amount' => 6000,
            'participants_count' => 1,
            'paid_at' => now(),
        ]);
        $booking->items()->create([
            'travel_category_id' => $categoryA->id,
            'category_code' => 'A',
            'category_name' => 'Plan A',
            'unit_price' => 6000,
            'quantity' => 1,
            'total_price' => 6000,
        ]);

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->getJson("/api/v1/travels/{$travel->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $travel->id);
        $response->assertJsonPath('data.province', $province->name);
        $response->assertJsonPath('data.available_slots', 5);
        $response->assertJsonPath('data.starting_price', '4000.00');
        $response->assertJsonPath('data.categories.0.remaining_count', 1);
        $response->assertJsonPath('data.categories.1.remaining_count', 3);
        $response->assertJsonPath('data.categories.2.remaining_count', 1);
        $response->assertJsonMissingPath('data.location');
        $response->assertJsonMissingPath('data.gallery_urls');
        $response->assertJsonMissingPath('data.currency');
        $response->assertJsonMissingPath('data.meeting_point');
        $response->assertJsonMissingPath('data.categories.0.code');
        $response->assertJsonMissingPath('data.categories.0.description');
    }

    public function test_show_includes_itinerary_file_when_uploaded(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 0, 0, 'Africa/Cairo'));

        $this->seedAuthenticatedUser();
        $travel = $this->seedTravel();
        $this->seedCategory($travel, ['code' => 'A', 'name' => ['en' => 'Plan A', 'ar' => 'خطة A'], 'price' => 6000, 'capacity' => 2]);

        $travel
            ->addMediaFromString('Detailed itinerary')
            ->usingFileName('travel-program.pdf')
            ->usingName('travel-program')
            ->toMediaCollection('itinerary_file');

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->getJson("/api/v1/travels/{$travel->id}");

        $response->assertOk();
        $this->assertStringStartsWith('http', (string) $response->json('data.itinerary_file_url'));
        $this->assertStringContainsString('travel-program.pdf', (string) $response->json('data.itinerary_file_url'));
        $response->assertJsonMissingPath('data.has_itinerary_file');
        $response->assertJsonMissingPath('data.itinerary_file_name');
    }

    public function test_booking_creates_pending_order_with_summary_payment_methods_and_actions(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 0, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $this->seedPaymentMethods();
        $this->fakeSubscriptionCharge(4000);
        $travel = $this->seedTravel([
            'title' => ['en' => 'Alexandria Travel', 'ar' => 'رحلة الإسكندرية'],
            'location' => ['en' => 'Alexandria', 'ar' => 'الإسكندرية'],
            'start_date' => '2026-08-22',
            'end_date' => '2026-08-22',
        ]);
        $categoryA = $this->seedCategory($travel, ['code' => 'A', 'name' => ['en' => 'Plan A', 'ar' => 'خطة A'], 'price' => 6000, 'capacity' => 10]);
        $categoryB = $this->seedCategory($travel, ['code' => 'B', 'name' => ['en' => 'Plan B', 'ar' => 'خطة B'], 'price' => 6000, 'capacity' => 10]);
        $categoryC = $this->seedCategory($travel, ['code' => 'C', 'name' => ['en' => 'Plan C', 'ar' => 'خطة C'], 'price' => 4000, 'capacity' => 10]);

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/travels/{$travel->id}/booking", [
                'items' => [
                    ['travel_category_id' => $categoryA->id, 'quantity' => 1],
                    ['travel_category_id' => $categoryB->id, 'quantity' => 2],
                    ['travel_category_id' => $categoryC->id, 'quantity' => 1],
                ],
            ]);

        $response->assertCreated();
        $response->assertJsonPath('message', 'Travel booking created successfully.');
        $response->assertJsonPath('data.order.request.type', 'travel_booking');
        $response->assertJsonPath('data.order.request.status', 'pending_payment');
        $response->assertJsonPath('data.order.request.travel.id', $travel->id);
        $response->assertJsonPath('data.order.request.participants_count', 4);
        $response->assertJsonPath('data.order.request.categories.0.label', 'Plan A');
        $response->assertJsonPath('data.order.request.categories.1.quantity', 2);
        $response->assertJsonPath('data.order.items.0.label', 'Plan A');
        $response->assertJsonPath('data.order.items.0.amount', '6000.00');
        $response->assertJsonPath('data.order.items.1.label', 'Plan B');
        $response->assertJsonPath('data.order.items.1.amount', '12000.00');
        $response->assertJsonPath('data.order.items.2.label', 'Plan C');
        $response->assertJsonPath('data.order.items.2.amount', '4000.00');
        $response->assertJsonPath('data.order.items.3.label', 'Subscription fees');
        $response->assertJsonPath('data.order.items.3.amount', '4000.00');
        $response->assertJsonPath('data.order.total', '26000.00');
        $response->assertJsonPath('data.payment_methods.0.key', 'fawry');
        $response->assertJsonPath('data.payment_methods.1.key', 'instapay');
        $this->assertNotNull($response->json('data.actions.pay_endpoint'));

        $bookingId = (int) $response->json('data.order.request.id');
        $orderId = (int) $response->json('data.order.id');

        $this->assertDatabaseHas('travel_bookings', [
            'id' => $bookingId,
            'travel_id' => $travel->id,
            'user_id' => $user->id,
            'status' => TravelBooking::STATUS_PENDING_PAYMENT,
            'participants_count' => 4,
            'total_amount' => 26000,
        ]);
        $this->assertSame(3, DB::table('travel_booking_items')->where('travel_booking_id', $bookingId)->count());
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'orderable_type' => TravelBooking::class,
            'orderable_id' => $bookingId,
            'amount' => 26000,
            'status' => TravelBooking::STATUS_PENDING_PAYMENT,
        ]);

        $order = Order::query()->findOrFail($orderId);
        $this->assertSame('4000.00', data_get($order->payload, 'subscription_charge.amount'));
    }

    public function test_pay_confirm_and_payment_history_include_travel_booking(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 0, 0, 'Africa/Cairo'));

        $this->seedAuthenticatedUser();
        $this->seedPaymentMethods();
        $this->fakeSubscriptionCharge(0);
        $travel = $this->seedTravel([
            'title' => ['en' => 'Alexandria Travel', 'ar' => 'رحلة الإسكندرية'],
            'location' => ['en' => 'Alexandria', 'ar' => 'الإسكندرية'],
            'start_date' => '2026-08-22',
            'end_date' => '2026-08-22',
        ]);
        $categoryA = $this->seedCategory($travel, ['code' => 'A', 'name' => ['en' => 'Plan A', 'ar' => 'خطة A'], 'price' => 6000, 'capacity' => 10]);
        $categoryB = $this->seedCategory($travel, ['code' => 'B', 'name' => ['en' => 'Plan B', 'ar' => 'خطة B'], 'price' => 6000, 'capacity' => 10]);

        $bookingResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/travels/{$travel->id}/booking", [
                'items' => [
                    ['travel_category_id' => $categoryA->id, 'quantity' => 1],
                    ['travel_category_id' => $categoryB->id, 'quantity' => 2],
                ],
            ]);

        $bookingResponse->assertCreated();
        $orderId = (int) $bookingResponse->json('data.order.id');
        $bookingId = (int) $bookingResponse->json('data.order.request.id');

        $checkoutResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/orders/{$orderId}/pay", [
                'payment_method' => 'instapay',
            ]);

        $checkoutResponse->assertOk();
        $checkoutResponse->assertJsonPath('data.order.request.type', 'travel_booking');
        $checkoutResponse->assertJsonPath('data.order.status', 'checkout_pending');
        $checkoutResponse->assertJsonPath('data.checkout.payment_method', 'instapay');

        $confirmResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/orders/{$orderId}/confirm-payment");

        $confirmResponse->assertOk();
        $confirmResponse->assertJsonPath('data.order.status', 'paid_successfully');
        $confirmResponse->assertJsonPath('data.order.request.status', 'paid_successfully');
        $confirmResponse->assertJsonPath('data.order.request.type', 'travel_booking');

        $this->assertDatabaseHas('travel_bookings', [
            'id' => $bookingId,
            'status' => 'paid_successfully',
            'paid_at' => '2026-08-10 10:00:00',
        ]);

        $paymentsResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->getJson('/api/v1/payments');

        $paymentsResponse->assertOk();
        $paymentsResponse->assertJsonPath('data.items.0.id', $orderId);
        $paymentsResponse->assertJsonPath('data.items.0.title', 'Alexandria Travel');
        $paymentsResponse->assertJsonPath('data.items.0.type', 'travel_booking');
        $paymentsResponse->assertJsonPath('data.items.0.payment_method.label', 'InstaPay');

        $paymentDetailResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->getJson("/api/v1/payments/{$orderId}");

        $paymentDetailResponse->assertOk();
        $paymentDetailResponse->assertJsonPath('data.title', 'Alexandria Travel');
        $paymentDetailResponse->assertJsonPath('data.request.type', 'travel_booking');
        $paymentDetailResponse->assertJsonPath('data.request.id', $bookingId);
        $paymentDetailResponse->assertJsonPath('data.items.0.label', 'Plan A');
        $paymentDetailResponse->assertJsonPath('data.items.0.amount', '6000.00');
        $paymentDetailResponse->assertJsonPath('data.items.1.label', 'Plan B');
        $paymentDetailResponse->assertJsonPath('data.items.1.amount', '12000.00');
        $paymentDetailResponse->assertJsonPath('data.total', '18000.00');
    }

    public function test_release_expired_travel_bookings_command_marks_booking_and_order_as_expired(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 0, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $travel = $this->seedTravel([
            'start_date' => '2026-08-22',
            'end_date' => '2026-08-22',
        ]);
        $category = $this->seedCategory($travel, ['code' => 'A', 'name' => ['en' => 'Plan A', 'ar' => 'خطة A'], 'price' => 6000, 'capacity' => 10]);

        $booking = TravelBooking::query()->create([
            'travel_id' => $travel->id,
            'user_id' => $user->id,
            'status' => TravelBooking::STATUS_PENDING_PAYMENT,
            'total_amount' => 6000,
            'participants_count' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $booking->items()->create([
            'travel_category_id' => $category->id,
            'category_code' => 'A',
            'category_name' => 'Plan A',
            'unit_price' => 6000,
            'quantity' => 1,
            'total_price' => 6000,
        ]);
        $booking->order()->create([
            'user_id' => $user->id,
            'amount' => 6000,
            'currency' => 'EGP',
            'status' => 'pending_payment',
            'checkout_url' => 'https://checkout.example.test/pay',
        ]);

        Carbon::setTestNow(Carbon::now()->addMinutes(6));

        Artisan::call('travels:release-expired-bookings');

        $this->assertDatabaseHas('travel_bookings', [
            'id' => $booking->id,
            'status' => 'payment_expired',
        ]);
        $this->assertDatabaseHas('orders', [
            'orderable_type' => TravelBooking::class,
            'orderable_id' => $booking->id,
            'status' => 'payment_expired',
            'gateway_status' => 'EXPIRED',
            'checkout_url' => null,
        ]);
    }

    private function seedAuthenticatedUser(array $attributes = []): User
    {
        $user = User::query()->create(array_merge([
            'name' => 'Travel User',
            'phone' => '01012345678',
            'email' => 'travel@example.com',
            'password' => bcrypt('secret123'),
            'national_id' => '29901011234567',
            'reg_number' => '12345',
            'active' => true,
            'notification_enabled' => true,
        ], $attributes));

        Sanctum::actingAs($user);

        return $user;
    }

    private function seedProvince(array $attributes = []): Province
    {
        return Province::query()->create(array_merge([
            'name' => ['en' => 'Alexandria', 'ar' => 'الإسكندرية'],
            'shipping_cost' => 0,
        ], $attributes));
    }

    private function seedTravel(array $attributes = []): Travel
    {
        return Travel::query()->create(array_merge([
            'title' => ['en' => 'Alexandria Travel', 'ar' => 'رحلة الإسكندرية'],
            'description' => ['en' => 'Travel details', 'ar' => 'تفاصيل الرحلة'],
            'location' => ['en' => 'Alexandria', 'ar' => 'الإسكندرية'],
            'province_id' => $this->seedProvince()->id,
            'meeting_point_title' => ['en' => 'Main meeting point', 'ar' => 'نقطة التجمع الرئيسية'],
            'meeting_point_description' => ['en' => 'Arrive one hour before departure.', 'ar' => 'الحضور قبل التحرك بساعة.'],
            'start_date' => '2026-08-22',
            'end_date' => '2026-08-22',
            'is_active' => true,
            'is_featured' => false,
        ], $attributes));
    }

    private function seedCategory(Travel $travel, array $attributes = []): TravelCategory
    {
        return TravelCategory::query()->create(array_merge([
            'travel_id' => $travel->id,
            'code' => 'A',
            'name' => ['en' => 'Plan A', 'ar' => 'خطة A'],
            'description' => ['en' => 'Category details', 'ar' => 'تفاصيل الفئة'],
            'price' => 6000,
            'capacity' => 10,
            'sort_order' => 0,
            'is_active' => true,
            'features' => ['Hotel', 'Transfer'],
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

    private function fakeSubscriptionCharge(float $amount = 0.0, int $years = 3, int $status = 200): void
    {
        app()->instance(SubscriptionChargeService::class, new class($amount, $years, $status) extends SubscriptionChargeService {
            public function __construct(
                private readonly float $amount,
                private readonly int $years,
                private readonly int $status,
            ) {
            }

            public function resolveForUser(User $user): array
            {
                return [
                    'register_no' => (string) $user->reg_number,
                    'amount' => $this->amount,
                    'years' => $this->years,
                    'status' => $this->status,
                ];
            }
        });
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
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();
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
            $table->string('category_name');
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('total_price', 10, 2)->default(0);
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
