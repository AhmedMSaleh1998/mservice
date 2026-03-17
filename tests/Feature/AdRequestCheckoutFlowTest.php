<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Ads\Models\AdRequest;
use Modules\Ads\Models\AdSpace;
use Modules\Core\Models\Order;
use Modules\Core\Models\PaymentMethod;
use Modules\Services\Models\Service;
use Modules\Users\Models\User;
use Tests\TestCase;

class AdRequestCheckoutFlowTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = storage_path('framework/testing/ad-request-checkout-flow.sqlite');

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
        Storage::fake('public');

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

    public function test_store_returns_summary_and_payment_methods_for_ads(): void
    {
        $user = $this->seedAuthenticatedUser();
        $adSpace = $this->seedAdSpace();
        $this->seedPaymentMethods();

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->post('/api/v1/ads', [
                'ad_space_id' => $adSpace->id,
                'duration_months' => 2,
                'ad_text' => 'Homepage campaign',
                'design_image' => UploadedFile::fake()->image('design.png'),
            ]);

        $response->assertCreated();
        $response->assertJsonPath('message', 'Order created successfully.');
        $response->assertJsonPath('data.order.request.status', 'pending_payment');
        $response->assertJsonPath('data.order.items.0.label', 'Ad space booking');
        $response->assertJsonPath('data.order.items.0.amount', '2000.00');
        $response->assertJsonPath('data.order.total', '2000.00');
        $response->assertJsonPath('data.payment_methods.0.key', 'fawry');

        $adRequestId = (int) $response->json('data.order.request.id');
        $orderId = (int) $response->json('data.order.id');
        $this->assertDatabaseHas('ad_requests', [
            'id' => $adRequestId,
            'design_image' => 'design.png',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'orderable_type' => AdRequest::class,
            'orderable_id' => $adRequestId,
            'amount' => 2000,
        ]);
        $this->assertDatabaseHas('ad_spaces', [
            'id' => $adSpace->id,
            'is_available' => 0,
        ]);
        $response->assertJsonMissingPath('data.actions');
    }

    public function test_store_locks_ad_space_and_blocks_another_reservation(): void
    {
        $user = $this->seedAuthenticatedUser();
        $adSpace = $this->seedAdSpace();
        $this->seedPaymentMethods();

        $this->post('/api/v1/ads', [
            'ad_space_id' => $adSpace->id,
            'duration_months' => 1,
            'ad_text' => 'First reservation',
            'design_image' => UploadedFile::fake()->image('design-one.png'),
        ])->assertCreated();

        Sanctum::actingAs(User::query()->create([
            'name' => 'Second User',
            'phone' => '01087654321',
            'email' => 'second@example.com',
            'password' => bcrypt('secret123'),
            'national_id' => '29901011234568',
            'reg_number' => '67890',
            'active' => true,
            'notification_enabled' => true,
        ]));

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->post('/api/v1/ads', [
                'ad_space_id' => $adSpace->id,
                'duration_months' => 1,
                'ad_text' => 'Second reservation',
                'design_image' => UploadedFile::fake()->image('design-two.png'),
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'The selected ad space id is invalid.');
    }

    public function test_pay_starts_mock_checkout_and_confirm_marks_ad_paid(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 16, 6, 15, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $adSpace = $this->seedAdSpace();
        $this->seedPaymentMethods();
        config()->set('services.fawry.enabled', false);

        $adRequest = AdRequest::query()->create([
            'user_id' => $user->id,
            'ad_space_id' => $adSpace->id,
            'duration_months' => 1,
            'price_per_month' => 1000,
            'total_amount' => 1000,
            'ad_text' => 'Test ad',
            'status' => 'pending_payment',
        ]);
        $order = $this->createOrderForAdRequest($adRequest);

        $checkoutResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/orders/{$order->id}/pay", [
                'payment_method' => 'fawry',
            ]);

        $checkoutResponse->assertOk();
        $checkoutResponse->assertJsonPath('data.order.request.status', 'pending_payment');
        $checkoutResponse->assertJsonPath('data.checkout.mode', 'mock');
        $checkoutResponse->assertJsonPath('data.checkout.payment_method', 'fawry');
        $checkoutResponse->assertJsonPath('data.order.payment_method', 'fawry');

        $confirmResponse = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/orders/{$order->id}/confirm-payment");

        $confirmResponse->assertOk();
        $confirmResponse->assertJsonPath('data.order.request.status', 'paid_successfully');
        $confirmResponse->assertJsonPath('data.order.status', 'paid_successfully');

        $this->assertDatabaseHas('ad_requests', [
            'id' => $adRequest->id,
            'status' => 'paid_successfully',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_method' => 'fawry',
            'status' => 'paid_successfully',
            'gateway_status' => 'PAID',
        ]);
        $this->assertDatabaseHas('ad_requests', [
            'id' => $adRequest->id,
            'starts_at' => '2026-03-16 06:15:00',
            'ends_at' => '2026-04-16 06:15:00',
        ]);
    }

    public function test_pay_starts_fawry_hosted_checkout_and_returns_payment_url(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 16, 6, 15, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $adSpace = $this->seedAdSpace();
        $this->seedPaymentMethods();
        $this->configureFawry();

        Http::fake([
            'https://atfawry.fawrystaging.com/fawrypay-api/api/payments/init' => Http::response([
                'statusCode' => 200,
                'statusDescription' => 'Operation done successfully',
                'type' => 'NEW',
                'referenceNumber' => '99887766',
                'url' => 'https://atfawry.fawrystaging.com/checkout/session-123',
            ], 200),
        ]);

        $adRequest = AdRequest::query()->create([
            'user_id' => $user->id,
            'ad_space_id' => $adSpace->id,
            'duration_months' => 1,
            'price_per_month' => 1000,
            'total_amount' => 1000,
            'ad_text' => 'Test ad',
            'status' => 'pending_payment',
        ]);
        $order = $this->createOrderForAdRequest($adRequest);

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/orders/{$order->id}/pay", [
                'payment_method' => 'fawry',
            ]);

        $response->assertOk();
        $response->assertExactJson([
            'payment_url' => 'https://atfawry.fawrystaging.com/checkout/session-123',
        ]);

        $merchantRefNum = null;
        $expectedExpiry = Carbon::now()->addMinutes(5)->getTimestampMs();

        Http::assertSent(function ($request) use ($adRequest, $expectedExpiry, &$merchantRefNum): bool {
            $merchantRefNum = $request['merchantRefNum'];

            return $request->url() === 'https://atfawry.fawrystaging.com/fawrypay-api/api/payments/init'
                && $request['merchantCode'] === 'TESTMERCHANT'
                && str_starts_with($merchantRefNum, "EMSAD{$adRequest->id}")
                && $request['amount'] === '1000.00'
                && $request['currencyCode'] === 'EGP'
                && $request['chargeItems'][0]['itemId'] === "ADREQ{$adRequest->id}"
                && $request['chargeItems'][0]['description'] === "Ad request {$adRequest->id}"
                && $request['paymentExpiry'] === $expectedExpiry
                && ! isset($request['paymentMethod']);
        });

        $this->assertNotNull($merchantRefNum);
        $this->assertNotSame("EMSAD{$adRequest->id}", $merchantRefNum);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_method' => 'fawry',
            'merchant_ref_num' => $merchantRefNum,
            'gateway_status' => 'NEW',
        ]);
    }

    public function test_pay_generates_a_new_fawry_reference_for_a_new_attempt(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 16, 6, 20, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $adSpace = $this->seedAdSpace();
        $this->seedPaymentMethods();
        $this->configureFawry();

        Http::fake([
            'https://atfawry.fawrystaging.com/fawrypay-api/api/payments/init' => Http::response([
                'statusCode' => 200,
                'statusDescription' => 'Operation done successfully',
                'type' => 'NEW',
                'referenceNumber' => '11223344',
                'url' => 'https://atfawry.fawrystaging.com/checkout/session-456',
            ], 200),
        ]);

        $adRequest = AdRequest::query()->create([
            'user_id' => $user->id,
            'ad_space_id' => $adSpace->id,
            'duration_months' => 1,
            'price_per_month' => 1000,
            'total_amount' => 1000,
            'ad_text' => 'Test ad',
            'status' => 'pending_payment',
        ]);

        $oldMerchantRefNum = "EMSAD{$adRequest->id}OLD";
        $order = $this->createOrderForAdRequest($adRequest, [
            'payment_method' => 'fawry',
            'provider' => 'fawry',
            'merchant_ref_num' => $oldMerchantRefNum,
            'gateway_status' => 'FAILED',
            'checkout_url' => null,
        ]);

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/orders/{$order->id}/pay", [
                'payment_method' => 'fawry',
            ]);

        $response->assertOk();
        $response->assertExactJson([
            'payment_url' => 'https://atfawry.fawrystaging.com/checkout/session-456',
        ]);

        $newMerchantRefNum = null;

        Http::assertSent(function ($request) use (&$newMerchantRefNum, $adRequest, $oldMerchantRefNum): bool {
            $newMerchantRefNum = $request['merchantRefNum'];

            return $request->url() === 'https://atfawry.fawrystaging.com/fawrypay-api/api/payments/init'
                && str_starts_with($newMerchantRefNum, "EMSAD{$adRequest->id}")
                && $newMerchantRefNum !== $oldMerchantRefNum;
        });

        $this->assertNotNull($newMerchantRefNum);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'merchant_ref_num' => $newMerchantRefNum,
            'gateway_status' => 'NEW',
        ]);
    }

    public function test_sync_payment_status_marks_ad_paid_when_fawry_reports_paid(): void
    {
        $user = $this->seedAuthenticatedUser();
        $adSpace = $this->seedAdSpace();
        $this->seedPaymentMethods();
        $this->configureFawry();

        $adRequest = AdRequest::query()->create([
            'user_id' => $user->id,
            'ad_space_id' => $adSpace->id,
            'duration_months' => 1,
            'price_per_month' => 1000,
            'total_amount' => 1000,
            'ad_text' => 'Test ad',
            'status' => 'pending_payment',
        ]);
        $order = $this->createOrderForAdRequest($adRequest, [
            'status' => 'checkout_pending',
            'payment_method' => 'fawry',
            'provider' => 'fawry',
            'merchant_ref_num' => 'AD-777',
            'gateway_status' => 'NEW',
            'checkout_url' => 'https://atfawry.fawrystaging.com/checkout/session-777',
        ]);

        $payload = [
            'fawryRefNumber' => '123456789',
            'merchantRefNumber' => 'AD-777',
            'paymentAmount' => '1000.00',
            'orderAmount' => '1000.00',
            'orderStatus' => 'PAID',
            'paymentMethod' => 'PayAtFawry',
            'paymentRefrenceNumber' => '556677',
        ];
        $payload['messageSignature'] = $this->buildFawryStatusSignature($payload);

        Http::fake([
            'https://atfawry.fawrystaging.com/ECommerceWeb/Fawry/payments/status/v2*' => Http::response($payload, 200),
        ]);

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/orders/{$order->id}/sync-payment-status");

        $response->assertOk();
        $response->assertJsonPath('data.order.request.status', 'paid_successfully');
        $response->assertJsonPath('data.order.gateway_status', 'PAID');

        $this->assertDatabaseHas('ad_requests', [
            'id' => $adRequest->id,
            'status' => 'paid_successfully',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid_successfully',
            'gateway_status' => 'PAID',
            'gateway_reference' => '123456789',
        ]);
    }

    public function test_fawry_return_marks_request_as_paid_when_signature_is_valid(): void
    {
        $user = $this->seedAuthenticatedUser();
        $adSpace = $this->seedAdSpace();
        $this->seedPaymentMethods();
        $this->configureFawry();

        $adRequest = AdRequest::query()->create([
            'user_id' => $user->id,
            'ad_space_id' => $adSpace->id,
            'duration_months' => 1,
            'price_per_month' => 1000,
            'total_amount' => 1000,
            'ad_text' => 'Test ad',
            'status' => 'pending_payment',
        ]);
        $order = $this->createOrderForAdRequest($adRequest, [
            'status' => 'checkout_pending',
            'payment_method' => 'fawry',
            'provider' => 'fawry',
            'merchant_ref_num' => 'AD-888',
            'gateway_status' => 'NEW',
            'checkout_url' => 'https://atfawry.fawrystaging.com/checkout/session-888',
        ]);

        $payload = [
            'statusCode' => 200,
            'statusDescription' => 'Operation done successfully',
            'referenceNumber' => '778899',
            'merchantRefNumber' => 'AD-888',
            'paymentAmount' => '1000.00',
            'orderAmount' => '1000.00',
            'orderStatus' => 'PAID',
            'paymentMethod' => 'PayAtFawry',
            'fawryFees' => '0.00',
            'shippingFees' => '0.00',
            'authNumber' => '',
            'customerMail' => $user->email,
            'customerMobile' => $user->phone,
        ];
        $payload['signature'] = $this->buildFawryReturnSignature($payload);

        $response = $this->get('/api/v1/payments/fawry/orders/return?' . http_build_query($payload));

        $response->assertOk();
        $response->assertJsonPath('data.order.request.status', 'paid_successfully');
        $response->assertJsonPath('data.order.gateway_status', 'PAID');

        $this->assertDatabaseHas('ad_requests', [
            'id' => $adRequest->id,
            'status' => 'paid_successfully',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid_successfully',
            'gateway_status' => 'PAID',
            'gateway_reference' => '778899',
        ]);
    }

    public function test_fawry_return_marks_request_pending_when_payment_is_unpaid(): void
    {
        $user = $this->seedAuthenticatedUser();
        $adSpace = $this->seedAdSpace();
        $this->seedPaymentMethods();
        $this->configureFawry();
        config()->set('services.fawry.frontend_return_url', 'https://frontend.example.test/payment-result');

        $adRequest = AdRequest::query()->create([
            'user_id' => $user->id,
            'ad_space_id' => $adSpace->id,
            'duration_months' => 1,
            'price_per_month' => 1000,
            'total_amount' => 1000,
            'ad_text' => 'Test ad',
            'status' => 'pending_payment',
        ]);
        $order = $this->createOrderForAdRequest($adRequest, [
            'status' => 'checkout_pending',
            'payment_method' => 'fawry',
            'provider' => 'fawry',
            'merchant_ref_num' => 'AD-999',
            'gateway_status' => 'NEW',
            'checkout_url' => 'https://atfawry.fawrystaging.com/checkout/session-999',
        ]);

        $payload = [
            'statusCode' => 200,
            'statusDescription' => 'Operation done successfully',
            'referenceNumber' => '990011',
            'merchantRefNumber' => 'AD-999',
            'paymentAmount' => '1000.00',
            'orderAmount' => '1000.00',
            'orderStatus' => 'UNPAID',
            'paymentMethod' => 'PayAtFawry',
            'fawryFees' => '0.00',
            'shippingFees' => '0.00',
            'authNumber' => '',
            'customerMail' => $user->email,
            'customerMobile' => $user->phone,
        ];
        $payload['signature'] = $this->buildFawryReturnSignature($payload);

        $response = $this->get('/api/v1/payments/fawry/orders/return?' . http_build_query($payload));

        $response->assertRedirect('https://frontend.example.test/payment-result?order_id=' . $order->id . '&ad_request_id=' . $adRequest->id . '&merchant_ref_num=AD-999&success=0&status_code=200&status_description=Operation+done+successfully&order_status=UNPAID&reference_number=990011');

        $this->assertDatabaseHas('ad_requests', [
            'id' => $adRequest->id,
            'status' => 'pending_payment',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'pending_payment',
            'gateway_status' => 'UNPAID',
            'gateway_reference' => '990011',
            'checkout_url' => null,
        ]);
    }

    public function test_fawry_return_rejects_paid_callback_when_payment_arrives_after_timeout(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 16, 6, 15, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $adSpace = $this->seedAdSpace();
        $adSpace->forceFill(['is_available' => false])->save();
        $this->seedPaymentMethods();
        $this->configureFawry();
        config()->set('services.fawry.frontend_return_url', 'https://frontend.example.test/payment-result');

        $adRequest = AdRequest::query()->create([
            'user_id' => $user->id,
            'ad_space_id' => $adSpace->id,
            'duration_months' => 1,
            'price_per_month' => 1000,
            'total_amount' => 1000,
            'ad_text' => 'Late paid ad',
            'status' => 'pending_payment',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $order = $this->createOrderForAdRequest($adRequest, [
            'status' => 'checkout_pending',
            'payment_method' => 'fawry',
            'provider' => 'fawry',
            'merchant_ref_num' => 'AD-LATE-1',
            'gateway_status' => 'NEW',
            'checkout_url' => 'https://atfawry.fawrystaging.com/checkout/session-late',
        ]);

        $latePaymentTime = Carbon::now()->addMinutes(16)->getTimestampMs();
        Carbon::setTestNow(Carbon::now()->addMinutes(16));

        $payload = [
            'statusCode' => 200,
            'statusDescription' => 'Operation done successfully',
            'referenceNumber' => '991122',
            'merchantRefNumber' => 'AD-LATE-1',
            'paymentAmount' => '1000.00',
            'orderAmount' => '1000.00',
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

        $response->assertRedirect('https://frontend.example.test/payment-result?order_id=' . $order->id . '&ad_request_id=' . $adRequest->id . '&merchant_ref_num=AD-LATE-1&success=0&status_code=200&status_description=Operation+done+successfully&order_status=PAID&reference_number=991122');

        $this->assertDatabaseHas('ad_spaces', [
            'id' => $adSpace->id,
            'is_available' => 1,
        ]);
        $this->assertDatabaseHas('ad_requests', [
            'id' => $adRequest->id,
            'status' => 'payment_expired',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'payment_expired',
            'gateway_status' => 'EXPIRED',
            'gateway_reference' => '991122',
            'checkout_url' => null,
        ]);
    }

    public function test_pay_rejects_expired_ad_request_reservation_and_releases_ad_space(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 16, 6, 15, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $adSpace = $this->seedAdSpace();
        $this->seedPaymentMethods();
        $this->configureFawry();

        $adSpace->forceFill(['is_available' => false])->save();

        $adRequest = AdRequest::query()->create([
            'user_id' => $user->id,
            'ad_space_id' => $adSpace->id,
            'duration_months' => 1,
            'price_per_month' => 1000,
            'total_amount' => 1000,
            'ad_text' => 'Expired ad',
            'status' => 'pending_payment',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $order = $this->createOrderForAdRequest($adRequest);

        Carbon::setTestNow(Carbon::now()->addMinutes(6));

        $response = $this
            ->withHeaders(['lang' => 'en'])
            ->postJson("/api/v1/orders/{$order->id}/pay", [
                'payment_method' => 'fawry',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Ad request reservation has expired.');

        $this->assertDatabaseHas('ad_spaces', [
            'id' => $adSpace->id,
            'is_available' => 1,
        ]);
        $this->assertDatabaseHas('ad_requests', [
            'id' => $adRequest->id,
            'status' => 'payment_expired',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'payment_expired',
            'gateway_status' => 'EXPIRED',
        ]);
    }

    public function test_release_expired_reservations_command_unlocks_ad_space(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 16, 6, 15, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $adSpace = $this->seedAdSpace();
        $adSpace->forceFill(['is_available' => false])->save();

        $adRequest = AdRequest::query()->create([
            'user_id' => $user->id,
            'ad_space_id' => $adSpace->id,
            'duration_months' => 1,
            'price_per_month' => 1000,
            'total_amount' => 1000,
            'ad_text' => 'Expired ad',
            'status' => 'pending_payment',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        $order = $this->createOrderForAdRequest($adRequest);

        Carbon::setTestNow(Carbon::now()->addMinutes(6));

        Artisan::call('ads:release-expired-reservations');

        $this->assertDatabaseHas('ad_spaces', [
            'id' => $adSpace->id,
            'is_available' => 1,
        ]);
        $this->assertDatabaseHas('ad_requests', [
            'id' => $adRequest->id,
            'status' => 'payment_expired',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'payment_expired',
            'gateway_status' => 'EXPIRED',
            'checkout_url' => null,
        ]);
    }

    public function test_release_expired_reservations_command_unlocks_completed_paid_booking(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 16, 6, 16, 0, 'Africa/Cairo'));

        $user = $this->seedAuthenticatedUser();
        $adSpace = $this->seedAdSpace();
        $adSpace->forceFill(['is_available' => false])->save();

        $adRequest = AdRequest::query()->create([
            'user_id' => $user->id,
            'ad_space_id' => $adSpace->id,
            'duration_months' => 1,
            'price_per_month' => 1000,
            'total_amount' => 1000,
            'ad_text' => 'Completed booking',
            'status' => 'paid_successfully',
            'starts_at' => Carbon::create(2026, 3, 16, 6, 15, 0, 'Africa/Cairo'),
            'ends_at' => Carbon::create(2026, 4, 16, 6, 15, 0, 'Africa/Cairo'),
            'created_at' => Carbon::create(2026, 3, 16, 6, 15, 0, 'Africa/Cairo'),
            'updated_at' => Carbon::create(2026, 3, 16, 6, 15, 0, 'Africa/Cairo'),
        ]);
        $this->createOrderForAdRequest($adRequest, [
            'status' => 'paid_successfully',
            'payment_method' => 'fawry',
            'provider' => 'fawry',
            'gateway_status' => 'PAID',
            'paid_at' => Carbon::create(2026, 3, 16, 6, 15, 0, 'Africa/Cairo'),
        ]);

        Artisan::call('ads:release-expired-reservations');

        $this->assertDatabaseHas('ad_spaces', [
            'id' => $adSpace->id,
            'is_available' => 1,
        ]);
        $this->assertDatabaseHas('ad_requests', [
            'id' => $adRequest->id,
            'status' => 'completed',
        ]);
    }

    private function seedAuthenticatedUser(): User
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'phone' => '01012345678',
            'email' => 'test@example.com',
            'password' => bcrypt('secret123'),
            'national_id' => '29901011234567',
            'reg_number' => '12345',
            'active' => true,
            'notification_enabled' => true,
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    private function seedAdSpace(): AdSpace
    {
        $service = Service::query()->create([
            'title' => ['en' => 'Homepage Banner', 'ar' => 'بانر الصفحة الرئيسية'],
            'description' => ['en' => 'Homepage banner service', 'ar' => 'خدمة بانر الصفحة الرئيسية'],
            'is_featured' => true,
            'is_active' => true,
        ]);

        return AdSpace::query()->create([
            'service_id' => $service->id,
            'max_characters' => 100,
            'min_duration_months' => 1,
            'price_per_month' => 1000,
            'is_available' => true,
            'order' => 1,
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

    private function buildFawryStatusSignature(array $payload): string
    {
        return hash('sha256',
            $payload['fawryRefNumber']
            . $payload['merchantRefNumber']
            . $this->formatAmount($payload['paymentAmount'])
            . $this->formatAmount($payload['orderAmount'])
            . $payload['orderStatus']
            . $payload['paymentMethod']
            . $payload['paymentRefrenceNumber']
            . 'TESTSECUREKEY'
        );
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

    private function createOrderForAdRequest(AdRequest $adRequest, array $attributes = []): Order
    {
        return $adRequest->order()->create(array_merge([
            'user_id' => $adRequest->user_id,
            'amount' => $adRequest->total_amount,
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
            $table->string('address')->nullable();
            $table->string('neqaba_address')->nullable();
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
            $table->json('payload')->nullable();
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
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable();
            $table->nullableTimestamps();
        });
    }
}
