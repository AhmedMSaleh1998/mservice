<?php

namespace App\Jobs;

use App\Services\Sms\CommunitySmsService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Modules\Notifications\Models\AdminNotification;
use Modules\Notifications\Models\DeviceToken;
use Modules\Notifications\Services\FcmService;
use Modules\Users\Models\User;
use Throwable;

class SendAdminNotificationChunk implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * SMS are sent one HTTP call at a time; past this budget the rest of the
     * chunk is handed to a follow-up job instead of running into the timeout.
     */
    private const SMS_TIME_BUDGET_SECONDS = 60;

    // A retry would send the same push and SMS a second time.
    public int $tries = 1;

    // Below the database queue's 90 second retry_after, so no second worker
    // ever picks the job up while it is still running.
    public int $timeout = 85;

    /**
     * @param  array<int, int>  $userIds
     * @param  array<int, string>  $channels
     */
    public function __construct(
        public int $notificationId,
        public array $userIds,
        public array $channels,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(FcmService $fcmService, CommunitySmsService $communitySmsService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $notification = AdminNotification::query()->find($this->notificationId);
        if (! $notification) {
            return;
        }

        $startedAt = microtime(true);
        $counts = [];

        if (in_array(AdminNotification::CHANNEL_FCM, $this->channels, true)) {
            $counts += $this->sendPush($notification, $fcmService);
        }

        if (in_array(AdminNotification::CHANNEL_SMS, $this->channels, true)) {
            $counts += $this->sendSms($notification, $communitySmsService, $startedAt);
        }

        $counts = array_filter($counts);
        if ($counts !== []) {
            AdminNotification::query()->whereKey($notification->id)->toBase()->incrementEach($counts);
        }
    }

    /**
     * @return array{fcm_sent_count: int, fcm_failed_count: int}
     */
    private function sendPush(AdminNotification $notification, FcmService $fcmService): array
    {
        // Only users who kept notifications switched on in the app get a push.
        $tokens = DeviceToken::query()
            ->whereIn('user_id', User::query()
                ->whereIn('id', $this->userIds)
                ->where('notification_enabled', true)
                ->select('id'))
            ->pluck('token')
            ->all();

        if ($tokens === []) {
            return ['fcm_sent_count' => 0, 'fcm_failed_count' => 0];
        }

        if (! $fcmService->isEnabled()) {
            Log::warning('Admin notification push skipped: FCM is disabled.', [
                'admin_notification_id' => $notification->id,
                'tokens' => count($tokens),
            ]);

            return ['fcm_sent_count' => 0, 'fcm_failed_count' => count($tokens)];
        }

        try {
            $result = $fcmService->send($tokens, $notification->title, $notification->body, [
                'type' => 'admin_notification',
                'notification_id' => $notification->id,
            ]);
        } catch (Throwable $exception) {
            Log::error('Admin notification push failed.', [
                'admin_notification_id' => $notification->id,
                'tokens' => count($tokens),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return ['fcm_sent_count' => 0, 'fcm_failed_count' => count($tokens)];
        }

        // Uninstalled apps and expired tokens: drop them so they are not retried.
        if ($result['invalid_tokens'] !== []) {
            DeviceToken::query()->whereIn('token', $result['invalid_tokens'])->delete();
        }

        return ['fcm_sent_count' => $result['sent'], 'fcm_failed_count' => $result['failed']];
    }

    /**
     * @return array{sms_sent_count: int, sms_failed_count: int}
     */
    private function sendSms(AdminNotification $notification, CommunitySmsService $communitySmsService, float $startedAt): array
    {
        $users = User::query()
            ->whereIn('id', $this->userIds)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('id')
            ->get(['id', 'phone']);

        if (! $communitySmsService->isEnabled()) {
            Log::warning('Admin notification SMS skipped: Community SMS is disabled.', [
                'admin_notification_id' => $notification->id,
                'recipients' => $users->count(),
            ]);

            return ['sms_sent_count' => 0, 'sms_failed_count' => $users->count()];
        }

        $sent = 0;
        $failed = 0;

        foreach ($users as $index => $user) {
            if (microtime(true) - $startedAt > self::SMS_TIME_BUDGET_SECONDS) {
                $this->continueSmsWith($users->slice($index)->pluck('id')->all());

                break;
            }

            try {
                $communitySmsService->send($user->phone, $notification->body, [
                    'type' => 'admin_notification',
                    'metadata' => [
                        'admin_notification_id' => $notification->id,
                        'user_id' => $user->id,
                    ],
                ]);
                $sent++;
            } catch (Throwable) {
                // The SMS service already recorded the failure in sms_messages.
                $failed++;
            }
        }

        return ['sms_sent_count' => $sent, 'sms_failed_count' => $failed];
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function continueSmsWith(array $userIds): void
    {
        $next = new self($this->notificationId, $userIds, [AdminNotification::CHANNEL_SMS]);

        if ($batch = $this->batch()) {
            $batch->add([$next]);

            return;
        }

        dispatch($next);
    }
}
