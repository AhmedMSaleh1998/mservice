<?php

namespace Tests\Unit;

use App\Services\Oracle\OraclePaymentSyncService;
use ReflectionMethod;
use Tests\TestCase;

class OraclePaymentSyncSuccessCodeTest extends TestCase
{
    private function isSuccess(string $paymentType, int|string|null $statusCode): bool
    {
        $service = app(OraclePaymentSyncService::class);
        $method = new ReflectionMethod($service, 'isSuccessfulPaymentResponse');
        $method->setAccessible(true);

        return $method->invoke($service, ['payment_type' => $paymentType], $statusCode);
    }

    public function test_status_code_1_is_success_for_every_payment_type(): void
    {
        // Oracle returns 1 ("تم دفع قيمة الخدمة") on a successful payment for services too, not only subscriptions.
        foreach (['subscription', 'certificate', 'course', 'restunit', 'membership'] as $type) {
            $this->assertTrue($this->isSuccess($type, 1), "status 1 should be success for {$type}");
        }
    }

    public function test_status_code_200_is_success(): void
    {
        $this->assertTrue($this->isSuccess('certificate', 200));
    }

    public function test_other_status_codes_are_failures(): void
    {
        // 5 = "يوجد خطا بالمبلغ" — a real Oracle rejection.
        $this->assertFalse($this->isSuccess('certificate', 5));
        $this->assertFalse($this->isSuccess('certificate', 0));
        $this->assertFalse($this->isSuccess('certificate', null));
    }
}
