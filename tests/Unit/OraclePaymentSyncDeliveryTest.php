<?php

namespace Tests\Unit;

use App\Services\Oracle\OraclePaymentSyncService;
use Modules\Certificates\Models\Certificate;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Core\Models\Order;
use Modules\Core\Models\Province;
use Modules\Memberships\Models\MembershipRequest;
use Modules\Services\Models\RestUnit;
use Modules\Services\Models\RestUnitBooking;
use Modules\Users\Models\User;
use Modules\Users\Models\UserAddress;
use ReflectionMethod;
use Tests\TestCase;

class OraclePaymentSyncDeliveryTest extends TestCase
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildParts(Order $order): array
    {
        $service = app(OraclePaymentSyncService::class);
        $method = new ReflectionMethod($service, 'buildPaymentDataParts');
        $method->setAccessible(true);

        return $method->invoke($service, $order);
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->forceFill(['id' => 1, 'reg_number' => '12345', 'phone' => '01012345678']);

        return $user;
    }

    private function makeAddress(int $regionId, float $shippingCost): UserAddress
    {
        $province = new Province();
        $province->forceFill(['id' => 5, 'delivery_region_id' => $regionId, 'shipping_cost' => $shippingCost]);

        $address = new UserAddress();
        $address->forceFill(['id' => 9, 'province_id' => 5]);
        $address->setRelation('province', $province);

        return $address;
    }

    public function test_certificate_with_home_delivery_sends_region_code_and_province_price(): void
    {
        $certificate = new Certificate();
        $certificate->forceFill(['id' => 7, 'pand_id' => 9981]);

        $certificateRequest = new CertificateRequest();
        $certificateRequest->forceFill(['id' => 3, 'phone' => '01099998888', 'delivery_method' => 'delivery', 'subscription_cost' => 0]);
        $certificateRequest->setRelation('certificate', $certificate);
        $certificateRequest->setRelation('userAddress', $this->makeAddress(2, 80));

        $order = new Order();
        $order->forceFill(['id' => 55, 'amount' => 300, 'payload' => []]);
        $order->setRelation('orderable', $certificateRequest);
        $order->setRelation('user', $this->makeUser());

        $parts = $this->buildParts($order);

        $this->assertCount(1, $parts);
        $this->assertSame(2, $parts[0]['delivered_type']);
        $this->assertSame('80.00', $parts[0]['delivered_price']);
    }

    public function test_certificate_without_delivery_sends_nulls(): void
    {
        $certificateRequest = new CertificateRequest();
        $certificateRequest->forceFill(['id' => 3, 'phone' => '01099998888', 'delivery_method' => 'branch', 'subscription_cost' => 0]);
        $certificateRequest->setRelation('certificate', new Certificate());
        $certificateRequest->setRelation('userAddress', $this->makeAddress(2, 80));

        $order = new Order();
        $order->forceFill(['id' => 56, 'amount' => 300, 'payload' => []]);
        $order->setRelation('orderable', $certificateRequest);
        $order->setRelation('user', $this->makeUser());

        $parts = $this->buildParts($order);

        $this->assertNull($parts[0]['delivered_type']);
        $this->assertNull($parts[0]['delivered_price']);
    }

    public function test_membership_delivery_goes_on_service_part_only(): void
    {
        $membershipRequest = new MembershipRequest();
        $membershipRequest->forceFill(['id' => 4, 'registration_number' => '12345']);
        $membershipRequest->setRelation('userAddress', $this->makeAddress(1, 50));

        $order = new Order();
        $order->forceFill(['id' => 57, 'amount' => 480, 'payload' => ['subscription_charge' => ['amount' => 430]]]);
        $order->setRelation('orderable', $membershipRequest);
        $order->setRelation('user', $this->makeUser());

        $parts = $this->buildParts($order);

        $this->assertCount(2, $parts);
        $this->assertSame('subscription', $parts[0]['payment_type']);
        $this->assertNull($parts[0]['delivered_type']);
        $this->assertNull($parts[0]['delivered_price']);
        $this->assertSame('membership', $parts[1]['payment_type']);
        $this->assertSame(1, $parts[1]['delivered_type']);
        $this->assertSame('50.00', $parts[1]['delivered_price']);
    }

    public function test_rest_unit_booking_sends_nulls(): void
    {
        $restUnit = new RestUnit();
        $restUnit->forceFill(['id' => 2, 'pand_id' => 7001]);

        $booking = new RestUnitBooking();
        $booking->forceFill(['id' => 6]);
        $booking->setRelation('restUnit', $restUnit);

        $order = new Order();
        $order->forceFill(['id' => 58, 'amount' => 500, 'payload' => []]);
        $order->setRelation('orderable', $booking);
        $order->setRelation('user', $this->makeUser());

        $parts = $this->buildParts($order);

        $this->assertNull($parts[0]['delivered_type']);
        $this->assertNull($parts[0]['delivered_price']);
    }

    public function test_delivery_without_resolvable_province_sends_nulls(): void
    {
        $certificateRequest = new CertificateRequest();
        $certificateRequest->forceFill(['id' => 3, 'phone' => '01099998888', 'delivery_method' => 'delivery', 'subscription_cost' => 0]);
        $certificateRequest->setRelation('certificate', new Certificate());
        $certificateRequest->setRelation('userAddress', null);

        $order = new Order();
        $order->forceFill(['id' => 59, 'amount' => 300, 'payload' => []]);
        $order->setRelation('orderable', $certificateRequest);
        $order->setRelation('user', $this->makeUser());

        $parts = $this->buildParts($order);

        $this->assertNull($parts[0]['delivered_type']);
        $this->assertNull($parts[0]['delivered_price']);
    }
}
