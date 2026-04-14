<?php

namespace Tests\Feature;

use App\Services\Oracle\OraclePaymentSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Modules\Certificates\Models\Certificate;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Core\Models\Order;
use Modules\Core\Models\PaymentMethod;
use Modules\Core\Models\Province;
use Modules\Core\Services\SubscriptionChargeService;
use Modules\Users\Models\User;
use Modules\Users\Models\UserAddress;
use Tests\TestCase;

class CertificateCheckoutFlowTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/certificate-checkout-flow.sqlite');

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

    public function test_certificates_index_returns_active_certificates_with_prices(): void
    {
        $this->seedAuthenticatedUser();

        Certificate::query()->create([
            'name' => ['en' => 'Practice Certificate', 'ar' => 'شهادة مزاولة مهنة'],
            'description' => ['en' => 'Practice Certificate', 'ar' => 'شهادة مزاولة مهنة'],
            'price' => 4500,
            'is_active' => true,
            'order' => 1,
        ]);

        Certificate::query()->create([
            'name' => ['en' => 'Hidden Certificate', 'ar' => 'شهادة مخفية'],
            'description' => ['en' => 'Hidden Certificate', 'ar' => 'شهادة مخفية'],
            'price' => 999,
            'is_active' => false,
            'order' => 2,
        ]);

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->getJson('/api/v1/certificates');

        $response->assertOk();
        $response->assertJsonPath('message', 'certificate list loaded successfully.');
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.price', '4500.00');
        $response->assertJsonPath('data.0.name', 'Practice Certificate');
    }

    public function test_store_creates_certificate_request_from_certificate_price_and_address_shipping_cost(): void
    {
        $user = $this->seedAuthenticatedUser();
        $certificate = $this->seedCertificate(3500);
        $address = $this->seedAddress($user, ['shipping_cost' => 250]);
        $this->seedPaymentMethods();

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson('/api/v1/certificate/request', [
                'certificate_id' => $certificate->id,
                'delivery_method' => 'delivery',
                'address_id' => $address->id,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('message', 'Order created successfully.');
        $response->assertJsonPath('data.order.request.type', 'certificate_request');
        $response->assertJsonPath('data.order.request.status', 'pending_payment');
        $response->assertJsonPath('data.order.request.delivery_status', 'pending');
        $response->assertJsonPath('data.order.request.certificate.id', $certificate->id);
        $response->assertJsonPath('data.order.request.certificate.price', '3500.00');
        $response->assertJsonPath('data.order.items.0.label', 'Certificate printing');
        $response->assertJsonPath('data.order.items.0.amount', '3500.00');
        $response->assertJsonPath('data.order.items.1.label', 'Shipping fees');
        $response->assertJsonPath('data.order.items.1.amount', '250.00');
        $response->assertJsonPath('data.order.total', '3750.00');
        $response->assertJsonPath('data.payment_methods.0.key', 'fawry');

        $certificateRequestId = (int) $response->json('data.order.request.id');
        $orderId = (int) $response->json('data.order.id');

        $this->assertDatabaseHas('certificate_requests', [
            'id' => $certificateRequestId,
            'user_id' => $user->id,
            'certificate_id' => $certificate->id,
            'delivery_method' => 'delivery',
            'user_address_id' => $address->id,
            'printing_cost' => 3500,
            'delivery_cost' => 250,
            'subscription_cost' => 0,
            'total_amount' => 3750,
            'status' => 'pending_payment',
            'delivery_status' => 'pending',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'orderable_type' => CertificateRequest::class,
            'orderable_id' => $certificateRequestId,
            'amount' => 3750,
            'status' => 'pending_payment',
        ]);
    }

    public function test_store_requires_owned_address_and_active_certificate(): void
    {
        $user = $this->seedAuthenticatedUser();
        $activeCertificate = $this->seedCertificate(3000);
        $inactiveCertificate = $this->seedCertificate(2800, ['is_active' => false]);
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
        $foreignAddress = $this->seedAddress($otherUser);

        $inactiveCertificateResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson('/api/v1/certificate/request', [
                'certificate_id' => $inactiveCertificate->id,
                'delivery_method' => 'digital',
                'email' => 'doctor@example.com',
            ]);

        $inactiveCertificateResponse->assertStatus(422);
        $inactiveCertificateResponse->assertJsonValidationErrors(['certificate_id']);

        $foreignAddressResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson('/api/v1/certificate/request', [
                'certificate_id' => $activeCertificate->id,
                'delivery_method' => 'delivery',
                'address_id' => $foreignAddress->id,
            ]);

        $foreignAddressResponse->assertStatus(422);
        $foreignAddressResponse->assertJsonValidationErrors(['address_id']);

        $this->assertDatabaseCount('certificate_requests', 0);
    }

    public function test_store_adds_subscription_item_when_oracle_returns_due_subscription(): void
    {
        $user = $this->seedAuthenticatedUser();
        $certificate = $this->seedCertificate(3500);
        $address = $this->seedAddress($user, ['shipping_cost' => 250]);
        $this->seedPaymentMethods();
        $this->fakeSubscriptionCharge();

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson('/api/v1/certificate/request', [
                'certificate_id' => $certificate->id,
                'delivery_method' => 'delivery',
                'address_id' => $address->id,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.order.items.0.label', 'Certificate printing');
        $response->assertJsonPath('data.order.items.0.amount', '3500.00');
        $response->assertJsonPath('data.order.items.1.label', 'Shipping fees');
        $response->assertJsonPath('data.order.items.1.amount', '250.00');
        $response->assertJsonPath('data.order.items.2.label', 'Subscription fees');
        $response->assertJsonPath('data.order.items.2.amount', '690.00');
        $response->assertJsonPath('data.order.total', '4440.00');

        $certificateRequestId = (int) $response->json('data.order.request.id');
        $orderId = (int) $response->json('data.order.id');

        $this->assertDatabaseHas('certificate_requests', [
            'id' => $certificateRequestId,
            'subscription_cost' => 690,
            'total_amount' => 4440,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'amount' => 4440,
        ]);

        $order = Order::query()->findOrFail($orderId);
        $this->assertSame('690.00', data_get($order->payload, 'subscription_charge.amount'));
        $this->assertSame(3, data_get($order->payload, 'subscription_charge.years'));
    }

    public function test_store_marks_free_certificate_request_as_paid_without_creating_order(): void
    {
        $user = $this->seedAuthenticatedUser();
        $certificate = $this->seedCertificate(0);
        $address = $this->seedAddress($user, ['shipping_cost' => 0]);
        $this->seedPaymentMethods();

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson('/api/v1/certificate/request', [
                'certificate_id' => $certificate->id,
                'delivery_method' => 'delivery',
                'address_id' => $address->id,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.order.id', null);
        $response->assertJsonPath('data.order.status', 'paid_successfully');
        $response->assertJsonPath('data.order.request.status', 'paid_successfully');
        $response->assertJsonPath('data.order.request.delivery_status', 'pending');
        $response->assertJsonPath('data.order.items.0.label', 'Certificate printing');
        $response->assertJsonPath('data.order.items.0.amount', '0.00');
        $response->assertJsonPath('data.order.total', '0.00');
        $response->assertJsonCount(0, 'data.payment_methods');

        $certificateRequestId = (int) $response->json('data.order.request.id');

        $this->assertDatabaseHas('certificate_requests', [
            'id' => $certificateRequestId,
            'user_id' => $user->id,
            'certificate_id' => $certificate->id,
            'total_amount' => 0,
            'status' => 'paid_successfully',
            'delivery_status' => 'pending',
        ]);
        $this->assertDatabaseMissing('orders', [
            'orderable_type' => CertificateRequest::class,
            'orderable_id' => $certificateRequestId,
        ]);
    }

    public function test_pay_and_confirm_mark_certificate_request_paid_successfully(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 31, 14, 0, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $certificate = $this->seedCertificate(4000);
        $address = $this->seedAddress($user, ['shipping_cost' => 150]);
        $this->seedPaymentMethods();
        config()->set('services.fawry.enabled', false);

        $certificateRequest = CertificateRequest::query()->create([
            'user_id' => $user->id,
            'certificate_id' => $certificate->id,
            'delivery_method' => 'delivery',
            'user_address_id' => $address->id,
            'status' => 'pending_payment',
            'delivery_status' => 'pending',
            'printing_cost' => 4000,
            'delivery_cost' => 150,
            'subscription_cost' => 0,
            'total_amount' => 4150,
        ]);
        $order = $certificateRequest->order()->create([
            'user_id' => $user->id,
            'amount' => 4150,
            'currency' => 'EGP',
            'status' => 'pending_payment',
        ]);

        $checkoutResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/orders/{$order->id}/pay", [
                'payment_method' => 'instapay',
            ]);

        $checkoutResponse->assertOk();
        $checkoutResponse->assertJsonPath('data.order.request.type', 'certificate_request');
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
                    'attempted_at' => '2026-03-31 14:00:00',
                    'synced_at' => '2026-03-31 14:00:00',
                    'payment_type' => 'certificate',
                    'status_code' => 200,
                    'message' => 'Oracle synced successfully.',
                    'request' => [
                        'registration_no' => '12345',
                        'amount' => '4150.00',
                        'payment_type' => 'certificate',
                        'bank_transaction_id' => 'CERT1',
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
        $confirmResponse->assertJsonPath('data.order.request.delivery_status', 'pending');
        $confirmResponse->assertJsonPath('data.order.status', 'paid_successfully');

        $this->assertDatabaseHas('certificate_requests', [
            'id' => $certificateRequest->id,
            'status' => 'paid_successfully',
            'delivery_status' => 'pending',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid_successfully',
            'payment_method' => 'instapay',
            'gateway_status' => 'PAID',
            'paid_at' => '2026-03-31 14:00:00',
        ]);

        $order->refresh();
        $this->assertSame('success', data_get($order->payload, 'oracle_payment_sync.status'));
        $this->assertSame('certificate', data_get($order->payload, 'oracle_payment_sync.payment_type'));
        $this->assertSame(200, data_get($order->payload, 'oracle_payment_sync.status_code'));
    }

    private function seedAuthenticatedUser(array $attributes = []): User
    {
        $user = User::query()->create(array_merge([
            'name' => 'Certificate User',
            'phone' => '01012345678',
            'email' => 'certificate@example.com',
            'password' => bcrypt('secret123'),
            'national_id' => '29901011234567',
            'reg_number' => '12345',
            'active' => true,
            'notification_enabled' => true,
        ], $attributes));

        Sanctum::actingAs($user);

        return $user;
    }

    private function seedCertificate(float $price, array $attributes = []): Certificate
    {
        return Certificate::query()->create(array_merge([
            'name' => ['en' => 'Practice Certificate', 'ar' => 'شهادة مزاولة مهنة'],
            'description' => ['en' => 'Practice Certificate', 'ar' => 'شهادة مزاولة مهنة'],
            'price' => $price,
            'is_active' => true,
            'order' => 1,
        ], $attributes));
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

        Schema::connection('sqlite')->create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->json('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('certificate_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('certificate_id');
            $table->string('delivery_method')->default('delivery');
            $table->unsignedBigInteger('user_address_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('pending_payment');
            $table->string('delivery_status')->nullable();
            $table->decimal('printing_cost', 10, 2)->default(0);
            $table->decimal('delivery_cost', 10, 2)->default(0);
            $table->decimal('subscription_cost', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
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
}
