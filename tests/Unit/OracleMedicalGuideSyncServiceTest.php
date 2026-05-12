<?php

namespace Tests\Unit;

use App\Services\Oracle\OracleMedicalGuideSyncService;
use ReflectionClass;
use Tests\TestCase;

class OracleMedicalGuideSyncServiceTest extends TestCase
{
    public function test_extract_specialty_name_reads_first_specialization_item(): void
    {
        $service = (new ReflectionClass(OracleMedicalGuideSyncService::class))
            ->newInstanceWithoutConstructor();

        $method = new \ReflectionMethod($service, 'extractSpecialtyName');
        $method->setAccessible(true);

        $payload = [
            'specialization' => [
                [
                    'general' => 'طب عام',
                    'main_sub' => 'طب حالات حرجة',
                    'detail' => 'طب حالات حرجة دقيقة',
                ],
            ],
        ];

        $this->assertSame('طب حالات حرجة دقيقة', $method->invoke($service, $payload));
    }

    public function test_extract_specialty_name_still_reads_direct_specialization_object(): void
    {
        $service = (new ReflectionClass(OracleMedicalGuideSyncService::class))
            ->newInstanceWithoutConstructor();

        $method = new \ReflectionMethod($service, 'extractSpecialtyName');
        $method->setAccessible(true);

        $payload = [
            'specialization' => [
                'general' => 'طب عام',
                'main_sub' => 'طب حالات حرجة',
                'detail' => 'طب حالات حرجة',
            ],
        ];

        $this->assertSame('طب حالات حرجة', $method->invoke($service, $payload));
    }
}
