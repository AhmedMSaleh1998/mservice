<?php

namespace Tests\Feature;

use App\Services\Oracle\OraclePaymentSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Models\Order;
use Modules\Core\Models\PaymentMethod;
use Modules\Core\Models\Province;
use Modules\Memberships\Models\MembershipRequest;
use Modules\Services\Models\Service;
use Modules\Users\Models\User;
use Modules\Users\Models\UserAddress;
use Tests\TestCase;

class MembershipCheckoutFlowTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/membership-checkout-flow.sqlite');

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

    public function test_store_creates_membership_request_from_authenticated_user_and_address_shipping_cost(): void
    {
        $user = $this->seedAuthenticatedUser();
        $this->seedMembershipService(4500);
        $address = $this->seedAddress($user, [
            'shipping_cost' => 250,
        ]);
        $this->seedPaymentMethods();

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson('/api/v1/membership/request', [
                'address_id' => $address->id,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('message', 'Order created successfully.');
        $response->assertJsonPath('data.order.request.type', 'membership_request');
        $response->assertJsonPath('data.order.request.status', 'pending_payment');
        $response->assertJsonPath('data.order.request.full_name', 'Membership User');
        $response->assertJsonPath('data.order.request.registration_number', '12345');
        $response->assertJsonPath('data.order.items.0.label', 'Membership printing');
        $response->assertJsonPath('data.order.items.0.amount', '4500.00');
        $response->assertJsonPath('data.order.items.1.label', 'Shipping fees');
        $response->assertJsonPath('data.order.items.1.amount', '250.00');
        $response->assertJsonPath('data.order.total', '4750.00');
        $response->assertJsonPath('data.payment_methods.0.key', 'fawry');

        $membershipRequestId = (int) $response->json('data.order.request.id');
        $orderId = (int) $response->json('data.order.id');

        $this->assertDatabaseHas('membership_requests', [
            'id' => $membershipRequestId,
            'user_id' => $user->id,
            'full_name' => 'Membership User',
            'specialty' => 'طبيب',
            'degree' => 'بكالوريوس طب وجراحة',
            'registration_number' => '12345',
            'delivery_method' => 'delivery',
            'user_address_id' => $address->id,
            'printing_cost' => 4500,
            'delivery_cost' => 250,
            'subscription_cost' => 0,
            'total_amount' => 4750,
            'status' => 'pending_payment',
            'delivery_status' => 'pending',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'orderable_type' => MembershipRequest::class,
            'orderable_id' => $membershipRequestId,
            'amount' => 4750,
            'status' => 'pending_payment',
        ]);
    }

    public function test_store_requires_address_and_enforces_ownership(): void
    {
        $user = $this->seedAuthenticatedUser();
        $this->seedMembershipService(3500);
        $this->seedPaymentMethods();

        $otherUser = User::query()->create([
            'name' => 'Other User',
            'phone' => '01087654321',
            'email' => 'other@example.com',
            'password' => bcrypt('secret123'),
            'national_id' => '29901011234568',
            'reg_number' => '67890',
            'active' => true,
            'notification_enabled' => true,
        ]);
        $otherAddress = $this->seedAddress($otherUser);

        $missingAddressResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson('/api/v1/membership/request', []);

        $missingAddressResponse->assertStatus(422);
        $missingAddressResponse->assertJsonValidationErrors(['address_id']);

        $foreignAddressResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson('/api/v1/membership/request', [
                'address_id' => $otherAddress->id,
            ]);

        $foreignAddressResponse->assertStatus(422);
        $foreignAddressResponse->assertJsonValidationErrors(['address_id']);
    }

    public function test_pay_and_confirm_mark_membership_request_paid(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 27, 15, 0, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser([
            'reg_number' => null,
        ]);
        $address = $this->seedAddress($user, [
            'shipping_cost' => 200,
        ]);
        $this->seedPaymentMethods();
        config()->set('services.fawry.enabled', false);

        $membershipRequest = MembershipRequest::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Membership User',
            'specialty' => 'طبيب',
            'degree' => 'بكالوريوس طب وجراحة',
            'registration_number' => 'TEMP-REGISTRATION-NUMBER',
            'delivery_method' => 'delivery',
            'status' => 'pending_payment',
            'delivery_status' => 'pending',
            'printing_cost' => 4000,
            'delivery_cost' => 200,
            'subscription_cost' => 0,
            'total_amount' => 4200,
            'user_address_id' => $address->id,
        ]);
        $order = $membershipRequest->order()->create([
            'user_id' => $user->id,
            'amount' => 4200,
            'currency' => 'EGP',
            'status' => 'pending_payment',
        ]);

        $checkoutResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/orders/{$order->id}/pay", [
                'payment_method' => 'instapay',
            ]);

        $checkoutResponse->assertOk();
        $checkoutResponse->assertJsonPath('data.order.request.type', 'membership_request');
        $checkoutResponse->assertJsonPath('data.checkout.mode', 'mock');
        $checkoutResponse->assertJsonPath('data.order.payment_method', 'instapay');

        app()->instance(OraclePaymentSyncService::class, new class extends OraclePaymentSyncService {
            public function __construct()
            {
            }

            public function syncPaidOrder(Order $order): array
            {
                return [
                    'status' => 'success',
                    'reason' => null,
                    'attempted_at' => '2026-03-27 15:00:00',
                    'synced_at' => '2026-03-27 15:00:00',
                    'payment_type' => 'subscription',
                    'status_code' => 200,
                    'message' => 'Oracle synced successfully.',
                    'request' => [
                        'registration_no' => 'TEMPREGISTRATIONNUMBER',
                        'amount' => '4200.00',
                        'payment_type' => 'subscription',
                        'bank_transaction_id' => 'MID1',
                        'course_id' => null,
                        'phone_number' => '01012345678',
                    ],
                ];
            }
        });

        $confirmResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/orders/{$order->id}/confirm-payment");

        $confirmResponse->assertOk();
        $confirmResponse->assertJsonPath('data.order.request.status', 'paid_successfully');
        $confirmResponse->assertJsonPath('data.order.status', 'paid_successfully');

        $this->assertDatabaseHas('membership_requests', [
            'id' => $membershipRequest->id,
            'status' => 'paid_successfully',
            'delivery_status' => 'pending',
            'payment_method' => 'instapay',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid_successfully',
            'payment_method' => 'instapay',
            'gateway_status' => 'PAID',
            'paid_at' => '2026-03-27 15:00:00',
        ]);

        $order->refresh();
        $this->assertSame('success', data_get($order->payload, 'oracle_payment_sync.status'));
        $this->assertSame('subscription', data_get($order->payload, 'oracle_payment_sync.payment_type'));
        $this->assertSame(200, data_get($order->payload, 'oracle_payment_sync.status_code'));
    }

    public function test_pay_starts_fawry_hosted_checkout_for_membership_and_returns_payment_url(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 27, 16, 0, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $address = $this->seedAddress($user, [
            'shipping_cost' => 300,
        ]);
        $this->seedPaymentMethods();
        $this->configureFawry();

        Http::fake([
            'https://atfawry.fawrystaging.com/fawrypay-api/api/payments/init' => Http::response([
                'statusCode' => 200,
                'statusDescription' => 'Operation done successfully',
                'type' => 'NEW',
                'referenceNumber' => '44556677',
                'url' => 'https://atfawry.fawrystaging.com/checkout/session-membership-123',
            ], 200),
        ]);

        $membershipRequest = MembershipRequest::query()->create([
            'user_id' => $user->id,
            'full_name' => 'Membership User',
            'specialty' => 'طبيب',
            'degree' => 'بكالوريوس طب وجراحة',
            'registration_number' => '12345',
            'delivery_method' => 'delivery',
            'status' => 'pending_payment',
            'delivery_status' => 'pending',
            'printing_cost' => 4500,
            'delivery_cost' => 300,
            'subscription_cost' => 0,
            'total_amount' => 4800,
            'user_address_id' => $address->id,
        ]);
        $order = $membershipRequest->order()->create([
            'user_id' => $user->id,
            'amount' => 4800,
            'currency' => 'EGP',
            'status' => 'pending_payment',
        ]);

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/orders/{$order->id}/pay", [
                'payment_method' => 'fawry',
            ]);

        $response->assertOk();
        $response->assertExactJson([
            'payment_url' => 'https://atfawry.fawrystaging.com/checkout/session-membership-123',
        ]);

        $merchantRefNum = null;
        $expectedExpiry = Carbon::now()->addMinutes(5)->getTimestampMs();

        Http::assertSent(function ($request) use ($membershipRequest, $expectedExpiry, &$merchantRefNum): bool {
            $merchantRefNum = $request['merchantRefNum'];

            return $request->url() === 'https://atfawry.fawrystaging.com/fawrypay-api/api/payments/init'
                && $request['merchantCode'] === 'TESTMERCHANT'
                && str_starts_with($merchantRefNum, "EMSMID{$membershipRequest->id}")
                && $request['amount'] === '4800.00'
                && $request['currencyCode'] === 'EGP'
                && $request['chargeItems'][0]['itemId'] === "MEMREQ{$membershipRequest->id}"
                && $request['chargeItems'][0]['description'] === "Membership request {$membershipRequest->id}"
                && $request['chargeItems'][0]['price'] === 4800.0
                && $request['chargeItems'][0]['quantity'] === 1
                && $request['paymentExpiry'] === $expectedExpiry
                && ! isset($request['paymentMethod']);
        });

        $this->assertNotNull($merchantRefNum);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_method' => 'fawry',
            'merchant_ref_num' => $merchantRefNum,
            'gateway_status' => 'NEW',
        ]);
    }

    private function seedAuthenticatedUser(array $attributes = []): User
    {
        $user = User::query()->create(array_merge([
            'name' => 'Membership User',
            'phone' => '01012345678',
            'email' => 'membership@example.com',
            'password' => bcrypt('secret123'),
            'national_id' => '29901011234567',
            'reg_number' => '12345',
            'active' => true,
            'notification_enabled' => true,
        ], $attributes));

        Sanctum::actingAs($user);

        return $user;
    }

    private function seedMembershipService(float $price = 4000): Service
    {
        return Service::query()->create([
            'title' => ['en' => 'Membership ID', 'ar' => 'استخراج كارنية عضوية'],
            'description' => ['en' => 'Membership ID', 'ar' => 'استخراج كارنية عضوية'],
            'key' => 'membership-id',
            'price' => $price,
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
            $table->string('address')->nullable();
            $table->string('neqaba_address')->nullable();
            $table->rememberToken();
            $table->timestamps();
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
            $table->string('delivery_method')->default('pickup');
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
    }
}
