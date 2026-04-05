<?php

namespace Tests\Feature;

use App\Services\Oracle\OraclePaymentSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Models\Order;
use Modules\Core\Models\PaymentMethod;
use Modules\Core\Services\SubscriptionChargeService;
use Modules\Core\Models\Province;
use Modules\Services\Models\RestUnit;
use Modules\Services\Models\RestUnitBooking;
use Modules\Users\Models\User;
use Tests\TestCase;

class RestUnitBookingFlowTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/rest-unit-booking-flow.sqlite');

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

    public function test_show_returns_room_type_specific_availability_for_selected_dates(): void
    {
        $user = $this->seedAuthenticatedUser();
        $province = $this->seedProvince();
        $unit = $this->seedRestUnit([
            'province_id' => $province->id,
            'single_rooms' => 1,
            'double_rooms' => 1,
            'triple_rooms' => 1,
        ]);

        RestUnitBooking::query()->create([
            'rest_unit_id' => $unit->id,
            'user_id' => $user->id,
            'unit_type' => RestUnit::TYPE_SINGLE_ROOM,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-23',
            'status' => RestUnitBooking::STATUS_PAID_SUCCESSFULLY,
            'total_price' => 24000,
            'paid_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/services/rest-units/{$unit->id}?from_date=2026-08-17&to_date=2026-08-23");

        $response->assertOk();
        $response->assertJsonPath('data.room_types.0.type', RestUnit::TYPE_SINGLE_ROOM);
        $response->assertJsonPath('data.room_types.0.available_count', 0);
        $response->assertJsonPath('data.room_types.0.total_price', '24000.00');
        $response->assertJsonPath('data.room_types.0.is_available', false);
        $response->assertJsonPath('data.room_types.1.type', RestUnit::TYPE_DOUBLE_ROOM);
        $response->assertJsonPath('data.room_types.1.available_count', 1);
        $response->assertJsonPath('data.room_types.1.total_price', '30000.00');
        $response->assertJsonPath('data.room_types.1.is_available', true);
        $response->assertJsonPath('data.room_types.2.type', RestUnit::TYPE_TRIPLE_ROOM);
        $response->assertJsonPath('data.room_types.2.available_count', 1);
        $response->assertJsonPath('data.room_types.2.total_price', '36000.00');
        $response->assertJsonPath('data.available_places', 2);
        $response->assertJsonPath('data.dates.nights', 6);
        $response->assertJsonMissingPath('data.province_id');
        $response->assertJsonMissingPath('data.single_rooms');
        $response->assertJsonMissingPath('data.gallery_urls');
        $response->assertJsonMissingPath('data.room_options');
        $response->assertJsonMissingPath('data.room_types.0.legacy_type');
        $response->assertJsonMissingPath('data.room_types.0.total_count');
        $response->assertJsonMissingPath('data.room_types.0.reserved_count');
        $response->assertJsonMissingPath('data.room_types.0.currency');
        $response->assertJsonMissingPath('data.room_types.0.availability_known');
    }

    public function test_show_hides_room_types_until_dates_are_selected(): void
    {
        $user = $this->seedAuthenticatedUser();
        $province = $this->seedProvince();
        $unit = $this->seedRestUnit([
            'province_id' => $province->id,
            'single_rooms' => 1,
            'double_rooms' => 1,
            'triple_rooms' => 1,
        ]);

        $response = $this->getJson("/api/v1/services/rest-units/{$unit->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $unit->id);
        $response->assertJsonPath('data.availability_requires_dates', true);
        $response->assertJsonPath('data.room_types', []);
        $response->assertJsonPath('data.dates', null);
        $response->assertJsonMissingPath('data.single_room_price');
        $response->assertJsonMissingPath('data.double_rooms');
        $response->assertJsonMissingPath('data.triple_room_price');
        $response->assertJsonMissingPath('data.single_bed');
    }

    public function test_show_rejects_past_start_date_and_end_date_before_start_date(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 0, 0, 'Africa/Cairo'));

        $this->seedAuthenticatedUser();
        $province = $this->seedProvince();
        $unit = $this->seedRestUnit([
            'province_id' => $province->id,
        ]);

        $pastDateResponse = $this->getJson("/api/v1/services/rest-units/{$unit->id}?from_date=2026-08-09&to_date=2026-08-10");

        $pastDateResponse->assertStatus(422);
        $pastDateResponse->assertJsonValidationErrors(['from_date']);

        $invalidRangeResponse = $this->getJson("/api/v1/services/rest-units/{$unit->id}?from_date=2026-08-10&to_date=2026-08-09");

        $invalidRangeResponse->assertStatus(422);
        $invalidRangeResponse->assertJsonValidationErrors(['to_date']);
    }

    public function test_index_supports_multiple_province_and_room_type_filters(): void
    {
        $user = $this->seedAuthenticatedUser();
        $cairo = $this->seedProvince(['name' => ['en' => 'Cairo', 'ar' => 'القاهرة']]);
        $giza = $this->seedProvince(['name' => ['en' => 'Giza', 'ar' => 'الجيزة']]);
        $alex = $this->seedProvince(['name' => ['en' => 'Alex', 'ar' => 'الإسكندرية']]);

        $unitA = $this->seedRestUnit([
            'province_id' => $cairo->id,
            'single_rooms' => 1,
        ]);
        $unitB = $this->seedRestUnit([
            'province_id' => $giza->id,
            'single_rooms' => 1,
        ]);
        $this->seedRestUnit([
            'province_id' => $alex->id,
            'single_rooms' => 1,
        ]);

        RestUnitBooking::query()->create([
            'rest_unit_id' => $unitB->id,
            'user_id' => $user->id,
            'unit_type' => RestUnit::TYPE_SINGLE_ROOM,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-23',
            'status' => RestUnitBooking::STATUS_PENDING_PAYMENT,
            'total_price' => 24000,
        ]);

        $response = $this->getJson('/api/v1/services/rest-units?province_ids[0]=' . $cairo->id . '&province_ids[1]=' . $giza->id . '&room_types[0]=single_room&from_date=2026-08-17&to_date=2026-08-23');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $unitA->id);
        $response->assertJsonPath('data.0.province.id', $cairo->id);
        $response->assertJsonPath('data.0.available_places', 3);
        $response->assertJsonMissingPath('data.0.room_types');
        $response->assertJsonMissingPath('data.0.cover_image_url');
        $response->assertJsonMissingPath('data.0.gallery_urls');
        $response->assertJsonMissingPath('data.0.single_room_price');
        $response->assertJsonMissingPath('meta');
        $this->assertStringEndsWith('/api/v1/services/rest-units?page=1', (string) $response->json('links.first'));
    }

    public function test_index_rejects_past_start_date(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 0, 0, 'Africa/Cairo'));

        $this->seedAuthenticatedUser();
        $province = $this->seedProvince();
        $this->seedRestUnit([
            'province_id' => $province->id,
        ]);

        $response = $this->getJson('/api/v1/services/rest-units?from_date=2026-08-09&to_date=2026-08-10');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['from_date']);
    }

    public function test_booking_creates_pending_order_with_payment_summary(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 0, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $this->seedPaymentMethods();
        $this->fakeSubscriptionCharge();
        $province = $this->seedProvince();
        $unit = $this->seedRestUnit([
            'province_id' => $province->id,
            'single_rooms' => 1,
            'single_room_price' => 4000,
        ]);

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson('/api/v1/services/rest-units/booking', [
                'rest_unit_id' => $unit->id,
                'unit_type' => RestUnit::TYPE_SINGLE_ROOM,
                'start_date' => '2026-08-17',
                'end_date' => '2026-08-23',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('message', 'Booking request submitted successfully.');
        $response->assertJsonPath('data.order.request.type', 'rest_unit_booking');
        $response->assertJsonPath('data.order.request.status', 'pending_payment');
        $response->assertJsonPath('data.order.request.rest_unit.id', $unit->id);
        $response->assertJsonPath('data.order.request.rest_unit.province.id', $province->id);
        $response->assertJsonPath('data.order.items.0.label', 'Stay fees');
        $response->assertJsonPath('data.order.items.0.amount', '24000.00');
        $response->assertJsonPath('data.order.items.1.label', 'Subscription fees');
        $response->assertJsonPath('data.order.items.1.amount', '690.00');
        $response->assertJsonPath('data.order.total', '24690.00');
        $response->assertJsonMissingPath('data.payment_methods');
        $response->assertJsonMissingPath('data.actions');
        $response->assertJsonMissingPath('data.order.request.rest_unit.province_id');
        $response->assertJsonMissingPath('data.order.request.rest_unit.single_rooms');
        $response->assertJsonMissingPath('data.order.request.rest_unit.single_room_price');
        $response->assertJsonMissingPath('data.order.request.rest_unit.double_rooms');
        $response->assertJsonMissingPath('data.order.request.rest_unit.triple_rooms');
        $response->assertJsonMissingPath('data.order.request.rest_unit.single_bed');
        $response->assertJsonMissingPath('data.order.request.rest_unit.gallery_urls');
        $response->assertJsonMissingPath('data.order.request.rest_unit.room_types');
        $response->assertJsonMissingPath('data.order.request.rest_unit.total_places');
        $response->assertJsonMissingPath('data.order.request.rest_unit.availability_requires_dates');

        $bookingId = (int) $response->json('data.order.request.id');
        $orderId = (int) $response->json('data.order.id');

        $this->assertDatabaseHas('rest_unit_bookings', [
            'id' => $bookingId,
            'rest_unit_id' => $unit->id,
            'user_id' => $user->id,
            'status' => RestUnitBooking::STATUS_PENDING_PAYMENT,
            'unit_type' => RestUnit::TYPE_SINGLE_ROOM,
            'total_price' => 24690,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'orderable_type' => RestUnitBooking::class,
            'orderable_id' => $bookingId,
            'amount' => 24690,
            'status' => RestUnitBooking::STATUS_PENDING_PAYMENT,
        ]);

        $order = Order::query()->findOrFail($orderId);
        $this->assertSame('single_room', data_get($order->payload, 'pricing.items.0.meta.unit_type'));
        $this->assertSame('690.00', data_get($order->payload, 'subscription_charge.amount'));
    }

    public function test_pay_creates_a_new_fawry_link_for_every_retry(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 0, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $this->seedPaymentMethods();
        $this->configureFawry();
        $province = $this->seedProvince();
        $unit = $this->seedRestUnit([
            'province_id' => $province->id,
            'single_rooms' => 1,
            'single_room_price' => 4000,
        ]);

        Http::fake([
            'https://atfawry.fawrystaging.com/fawrypay-api/api/payments/init' => Http::response([
                'statusCode' => 200,
                'statusDescription' => 'Operation done successfully',
                'type' => 'NEW',
                'referenceNumber' => '778899',
                'url' => 'https://atfawry.fawrystaging.com/checkout/session-rest-unit-new',
            ], 200),
        ]);

        $booking = RestUnitBooking::query()->create([
            'rest_unit_id' => $unit->id,
            'user_id' => $user->id,
            'unit_type' => RestUnit::TYPE_SINGLE_ROOM,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-19',
            'status' => RestUnitBooking::STATUS_PENDING_PAYMENT,
            'total_price' => 8000,
        ]);

        $oldMerchantRefNum = "EMSRUB{$booking->id}OLD";
        $order = $this->createOrderForRestUnitBooking($booking, [
            'status' => 'checkout_pending',
            'payment_method' => 'fawry',
            'provider' => 'fawry',
            'merchant_ref_num' => $oldMerchantRefNum,
            'gateway_status' => 'NEW',
            'checkout_url' => 'https://atfawry.fawrystaging.com/checkout/session-rest-unit-old',
        ]);

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/orders/{$order->id}/pay", [
                'payment_method' => 'fawry',
            ]);

        $response->assertOk();
        $response->assertExactJson([
            'payment_url' => 'https://atfawry.fawrystaging.com/checkout/session-rest-unit-new',
        ]);

        $newMerchantRefNum = null;

        Http::assertSent(function ($request) use (&$newMerchantRefNum, $booking, $oldMerchantRefNum): bool {
            $newMerchantRefNum = $request['merchantRefNum'];

            return $request->url() === 'https://atfawry.fawrystaging.com/fawrypay-api/api/payments/init'
                && $request['paymentMethod'] === 'PayAtFawry'
                && str_starts_with($newMerchantRefNum, "EMSRUB{$booking->id}")
                && $newMerchantRefNum !== $oldMerchantRefNum;
        });

        $this->assertNotNull($newMerchantRefNum);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'merchant_ref_num' => $newMerchantRefNum,
            'gateway_status' => 'NEW',
            'checkout_url' => 'https://atfawry.fawrystaging.com/checkout/session-rest-unit-new',
        ]);
    }

    public function test_pay_and_confirm_mark_rest_unit_booking_paid(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 0, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $this->seedPaymentMethods();
        config()->set('services.fawry.enabled', false);
        $this->fakeOraclePaymentSync();

        $province = $this->seedProvince();
        $unit = $this->seedRestUnit([
            'province_id' => $province->id,
        ]);

        $booking = RestUnitBooking::query()->create([
            'rest_unit_id' => $unit->id,
            'user_id' => $user->id,
            'unit_type' => RestUnit::TYPE_SINGLE_ROOM,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-18',
            'status' => RestUnitBooking::STATUS_PENDING_PAYMENT,
            'total_price' => 4000,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $order = $this->createOrderForRestUnitBooking($booking);

        $checkoutResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/orders/{$order->id}/pay", [
                'payment_method' => 'instapay',
            ]);

        $checkoutResponse->assertOk();
        $checkoutResponse->assertJsonPath('data.order.request.type', 'rest_unit_booking');
        $checkoutResponse->assertJsonPath('data.order.request.status', 'pending_payment');
        $checkoutResponse->assertJsonPath('data.order.status', 'checkout_pending');
        $checkoutResponse->assertJsonPath('data.checkout.payment_method', 'instapay');

        $confirmResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/orders/{$order->id}/confirm-payment");

        $confirmResponse->assertOk();
        $confirmResponse->assertJsonPath('data.order.status', 'paid_successfully');
        $confirmResponse->assertJsonPath('data.order.request.status', 'paid_successfully');
        $confirmResponse->assertJsonPath('data.order.request.type', 'rest_unit_booking');

        $this->assertDatabaseHas('rest_unit_bookings', [
            'id' => $booking->id,
            'status' => 'paid_successfully',
            'paid_at' => '2026-08-10 10:00:00',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid_successfully',
            'payment_method' => 'instapay',
            'provider' => 'manual',
            'gateway_status' => 'PAID',
            'paid_at' => '2026-08-10 10:00:00',
        ]);
    }

    public function test_release_expired_rest_unit_bookings_command_marks_booking_and_order_as_payment_expired(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 10, 10, 0, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $province = $this->seedProvince();
        $unit = $this->seedRestUnit([
            'province_id' => $province->id,
        ]);

        $booking = RestUnitBooking::query()->create([
            'rest_unit_id' => $unit->id,
            'user_id' => $user->id,
            'unit_type' => RestUnit::TYPE_SINGLE_ROOM,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-18',
            'status' => RestUnitBooking::STATUS_PENDING_PAYMENT,
            'total_price' => 4000,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $order = $this->createOrderForRestUnitBooking($booking, [
            'checkout_url' => 'https://checkout.example.test/pay',
        ]);

        Carbon::setTestNow(Carbon::now()->addMinutes(6));

        Artisan::call('rest-units:release-expired-bookings');

        $this->assertDatabaseHas('rest_unit_bookings', [
            'id' => $booking->id,
            'status' => 'payment_expired',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'payment_expired',
            'gateway_status' => 'EXPIRED',
            'checkout_url' => null,
        ]);
    }

    private function seedAuthenticatedUser(array $attributes = []): User
    {
        $user = User::query()->create(array_merge([
            'name' => 'Rest Unit User',
            'phone' => '01012345678',
            'email' => 'rest-unit@example.com',
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
            'name' => ['en' => 'Cairo', 'ar' => 'القاهرة'],
            'shipping_cost' => 0,
        ], $attributes));
    }

    private function seedRestUnit(array $attributes = []): RestUnit
    {
        return RestUnit::query()->create(array_merge([
            'name' => ['en' => 'Al Ainy Rest House', 'ar' => 'استراحة القصر العيني'],
            'address' => ['en' => 'Cairo, Egypt', 'ar' => 'القاهرة، مصر'],
            'province_id' => $this->seedProvince()->id,
            'single_rooms' => 1,
            'double_rooms' => 1,
            'triple_rooms' => 1,
            'is_active' => true,
            'single_room_price' => 4000,
            'double_room_price' => 5000,
            'triple_room_price' => 6000,
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

    private function createOrderForRestUnitBooking(RestUnitBooking $booking, array $attributes = []): Order
    {
        return $booking->order()->create(array_merge([
            'user_id' => $booking->user_id,
            'amount' => $booking->total_price,
            'currency' => 'EGP',
            'status' => 'pending_payment',
        ], $attributes));
    }

    private function fakeSubscriptionCharge(float $amount = 690.0, int $years = 3, int $status = 200): void
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

    private function fakeOraclePaymentSync(): void
    {
        app()->instance(OraclePaymentSyncService::class, new class extends OraclePaymentSyncService {
            public function __construct()
            {
            }

            public function syncPaidOrder(Order $order): array
            {
                return [
                    'status' => 'skipped',
                    'reason' => 'unsupported_payment_type',
                    'attempted_at' => '2026-08-10 10:00:00',
                    'synced_at' => null,
                    'payment_type' => null,
                    'status_code' => null,
                    'message' => 'This paid order type is not supported by Oracle PAYMENT_SERVICE.',
                    'request' => null,
                ];
            }
        });
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

        Schema::connection('sqlite')->create('rest_units', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->json('address')->nullable();
            $table->unsignedBigInteger('province_id');
            $table->unsignedInteger('single_rooms')->default(0);
            $table->decimal('single_room_price', 10, 2)->default(0);
            $table->unsignedInteger('double_rooms')->default(0);
            $table->decimal('double_room_price', 10, 2)->default(0);
            $table->unsignedInteger('triple_rooms')->default(0);
            $table->decimal('triple_room_price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('rest_unit_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('rest_unit_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('unit_type')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('pending_payment');
            $table->decimal('total_price', 10, 2)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
