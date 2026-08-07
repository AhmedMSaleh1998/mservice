<?php

namespace Tests\Unit;

use App\Services\Oracle\OraclePaymentSyncService;
use Modules\Certificates\Models\Certificate;
use Modules\Certificates\Models\CertificateRequest;
use Modules\Core\Models\Order;
use Modules\Courses\Models\Course;
use Modules\Courses\Models\CourseBooking;
use Modules\Memberships\Models\MembershipRequest;
use Modules\Services\Models\RestUnit;
use Modules\Services\Models\RestUnitBooking;
use Modules\Users\Models\User;
use ReflectionMethod;
use Tests\TestCase;

class OraclePaymentSyncPandIdTest extends TestCase
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

    public function test_certificate_service_part_carries_certificate_pand_id(): void
    {
        $certificate = new Certificate();
        $certificate->forceFill(['id' => 7, 'pand_id' => 9981]);

        $certificateRequest = new CertificateRequest();
        $certificateRequest->forceFill(['id' => 3, 'phone' => '01099998888', 'subscription_cost' => 0]);
        $certificateRequest->setRelation('certificate', $certificate);

        $order = new Order();
        $order->forceFill(['id' => 55, 'amount' => 300, 'payload' => []]);
        $order->setRelation('orderable', $certificateRequest);
        $order->setRelation('user', $this->makeUser());

        $parts = $this->buildParts($order);

        $this->assertCount(1, $parts);
        $this->assertSame('certificate', $parts[0]['payment_type']);
        $this->assertSame(9981, $parts[0]['pand_id']);
    }

    public function test_certificate_order_splits_subscription_part_with_null_pand_id(): void
    {
        $certificate = new Certificate();
        $certificate->forceFill(['id' => 7, 'pand_id' => 9981]);

        $certificateRequest = new CertificateRequest();
        $certificateRequest->forceFill(['id' => 3, 'phone' => '01099998888', 'subscription_cost' => 0]);
        $certificateRequest->setRelation('certificate', $certificate);

        $order = new Order();
        // 100 of the 300 is a subscription charge; the remaining 200 is the certificate service amount.
        $order->forceFill([
            'id' => 55,
            'amount' => 300,
            'payload' => ['subscription_charge' => ['amount' => '100.00', 'years' => 1]],
        ]);
        $order->setRelation('orderable', $certificateRequest);
        $order->setRelation('user', $this->makeUser());

        $parts = $this->buildParts($order);

        $this->assertCount(2, $parts);

        $subscription = collect($parts)->firstWhere('payment_type', 'subscription');
        $service = collect($parts)->firstWhere('payment_type', 'certificate');

        // The subscription line has no certificate → null.
        $this->assertNotNull($subscription);
        $this->assertNull($subscription['pand_id']);

        // The certificate line carries the Oracle certificate number.
        $this->assertNotNull($service);
        $this->assertSame(9981, $service['pand_id']);
    }

    public function test_non_certificate_payment_has_null_pand_id(): void
    {
        $membershipRequest = new MembershipRequest();
        $membershipRequest->forceFill(['id' => 4, 'registration_number' => '12345', 'subscription_cost' => 0]);

        $order = new Order();
        $order->forceFill(['id' => 60, 'amount' => 500, 'payload' => []]);
        $order->setRelation('orderable', $membershipRequest);
        $order->setRelation('user', $this->makeUser());

        $parts = $this->buildParts($order);

        $this->assertCount(1, $parts);
        $this->assertSame('membership', $parts[0]['payment_type']);
        $this->assertNull($parts[0]['pand_id']);
    }

    public function test_rest_unit_part_uses_restunit_type_and_rest_unit_pand_id(): void
    {
        $restUnit = new RestUnit();
        $restUnit->forceFill(['id' => 12, 'pand_id' => 5501]);

        $restUnitBooking = new RestUnitBooking();
        $restUnitBooking->forceFill(['id' => 9, 'subscription_cost' => 0]);
        $restUnitBooking->setRelation('restUnit', $restUnit);

        $order = new Order();
        $order->forceFill(['id' => 70, 'amount' => 800, 'payload' => []]);
        $order->setRelation('orderable', $restUnitBooking);
        $order->setRelation('user', $this->makeUser());

        $parts = $this->buildParts($order);

        $this->assertCount(1, $parts);
        $this->assertSame('restunit', $parts[0]['payment_type']);
        $this->assertSame(5501, $parts[0]['pand_id']);
    }

    public function test_course_part_carries_course_pand_id(): void
    {
        $course = new Course();
        $course->forceFill(['id' => 22, 'pand_id' => 7702]);

        $courseBooking = new CourseBooking();
        $courseBooking->forceFill(['id' => 8, 'course_id' => 22, 'subscription_cost' => 0]);
        $courseBooking->setRelation('course', $course);

        $order = new Order();
        $order->forceFill(['id' => 71, 'amount' => 600, 'payload' => []]);
        $order->setRelation('orderable', $courseBooking);
        $order->setRelation('user', $this->makeUser());

        $parts = $this->buildParts($order);

        $this->assertCount(1, $parts);
        $this->assertSame('course', $parts[0]['payment_type']);
        $this->assertSame(7702, $parts[0]['pand_id']);
        $this->assertSame(22, $parts[0]['course_id']);
    }
}
