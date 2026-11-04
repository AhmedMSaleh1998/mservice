<?php

namespace Tests\Unit;

use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\AuthenticationError;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Modules\Notifications\Enums\FcmErrorCodeEnum;
use Modules\Notifications\Services\FcmService;
use Tests\TestCase;

class FcmServiceTest extends TestCase
{
    public function test_tokens_from_another_firebase_project_are_reported_for_removal(): void
    {
        $this->fakeMulticastReport([
            SendReport::success(MessageTarget::with(MessageTarget::TOKEN, 'current-token'), ['name' => 'ok']),
            SendReport::failure(MessageTarget::with(MessageTarget::TOKEN, 'old-project-token'), $this->fcmError(AuthenticationError::class, 403, FcmErrorCodeEnum::SENDER_ID_MISMATCH)),
            SendReport::failure(MessageTarget::with(MessageTarget::TOKEN, 'uninstalled-token'), $this->fcmError(NotFound::class, 404, FcmErrorCodeEnum::UNREGISTERED)),
        ]);

        $result = app(FcmService::class)->send(['current-token', 'old-project-token', 'uninstalled-token'], 'Title', 'Body');

        $this->assertSame(1, $result['sent']);
        $this->assertSame(2, $result['failed']);
        $this->assertEqualsCanonicalizing(['old-project-token', 'uninstalled-token'], $result['invalid_tokens']);
    }

    public function test_a_credentials_problem_never_marks_tokens_for_removal(): void
    {
        // A 403 without a token-specific FCM error code means our own service
        // account is the problem; the devices' tokens are still good.
        $this->fakeMulticastReport([
            SendReport::failure(MessageTarget::with(MessageTarget::TOKEN, 'good-token'), (new AuthenticationError('Permission denied'))->withErrors([
                'error' => ['code' => 403, 'message' => 'Permission denied', 'status' => 'PERMISSION_DENIED'],
            ])),
        ]);

        $result = app(FcmService::class)->send(['good-token'], 'Title', 'Body');

        $this->assertSame(1, $result['failed']);
        $this->assertSame([], $result['invalid_tokens']);
    }

    /**
     * @param  class-string<AuthenticationError|NotFound>  $exception
     */
    private function fcmError(string $exception, int $status, FcmErrorCodeEnum $code): AuthenticationError|NotFound
    {
        return (new $exception('FCM rejected the token'))->withErrors([
            'error' => [
                'code' => $status,
                'message' => 'FCM rejected the token',
                'details' => [
                    ['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => $code->value],
                ],
            ],
        ]);
    }

    /**
     * @param  array<int, SendReport>  $items
     */
    private function fakeMulticastReport(array $items): void
    {
        $messaging = $this->createMock(Messaging::class);
        $messaging->method('sendMulticast')->willReturn(MulticastSendReport::withItems($items));

        $this->app->instance(Messaging::class, $messaging);
    }
}
