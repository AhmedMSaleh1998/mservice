<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureDoctorHasNoSituation;
use App\Services\DoctorSituationService;
use Illuminate\Http\Request;
use Modules\Users\Models\User;
use Tests\TestCase;

class EnsureDoctorHasNoSituationTest extends TestCase
{
    private function middleware(bool $hasSituation): EnsureDoctorHasNoSituation
    {
        $service = new class($hasSituation) extends DoctorSituationService {
            public function __construct(private readonly bool $hasSituation)
            {
            }

            public function userHasSituation(User $user): bool
            {
                return $this->hasSituation;
            }
        };

        return new EnsureDoctorHasNoSituation($service);
    }

    private function requestForUser(?User $user): Request
    {
        $request = Request::create('/api/v1/certificate/request', 'POST');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_blocks_user_with_syndicate_situation(): void
    {
        app()->setLocale('ar');

        $response = $this->middleware(true)->handle(
            $this->requestForUser(new User(['reg_number' => '12345'])),
            fn () => response()->json(['ok' => true]),
        );

        $this->assertSame(403, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertSame('يوجد موقف مسجّل عليك لدى النقابة، برجاء التواصل مع النقابة لتسوية الأمر.', $payload['message']);
        $this->assertSame(403, $payload['status']);
    }

    public function test_allows_user_without_situation(): void
    {
        $response = $this->middleware(false)->handle(
            $this->requestForUser(new User(['reg_number' => '12345'])),
            fn () => response()->json(['ok' => true]),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['ok']);
    }

    public function test_allows_request_without_authenticated_user(): void
    {
        $response = $this->middleware(true)->handle(
            $this->requestForUser(null),
            fn () => response()->json(['ok' => true]),
        );

        $this->assertSame(200, $response->getStatusCode());
    }
}
