<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Modules\Notifications\Models\AdminNotification;
use Modules\Notifications\Models\UserNotification;
use Modules\Notifications\Services\AdminNotificationService;

/**
 * Fills every recipient's inbox, then fans the sending out into a batch of
 * small chunk jobs so thousands of SMS never run inside a single job.
 */
class DispatchAdminNotification implements ShouldQueue
{
    use Queueable;

    private const CHUNK_SIZE = 100;

    public int $tries = 1;

    public int $timeout = 85;

    public function __construct(public int $notificationId)
    {
        $this->onQueue('notifications');
    }

    public function handle(AdminNotificationService $adminNotificationService): void
    {
        $notification = AdminNotification::query()->find($this->notificationId);

        if (! $notification || $notification->status !== AdminNotification::STATUS_QUEUED) {
            return;
        }

        $notification->update(['status' => AdminNotification::STATUS_SENDING]);

        $jobs = [];
        $recipientsCount = 0;

        $adminNotificationService->recipientsQuery($notification)
            ->select('id')
            ->chunkById(self::CHUNK_SIZE, function ($users) use ($notification, &$jobs, &$recipientsCount): void {
                $userIds = $users->pluck('id')->all();
                $now = now();

                UserNotification::query()->insertOrIgnore(array_map(static fn (int $userId): array => [
                    'user_id' => $userId,
                    'admin_notification_id' => $notification->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $userIds));

                $jobs[] = new SendAdminNotificationChunk($notification->id, $userIds, $notification->channels ?? []);
                $recipientsCount += count($userIds);
            });

        $notification->update(['recipients_count' => $recipientsCount]);

        if ($jobs === []) {
            $notification->update([
                'status' => AdminNotification::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            return;
        }

        $notificationId = $notification->id;

        $batch = Bus::batch($jobs)
            ->name("admin-notification-{$notificationId}")
            ->allowFailures()
            ->onQueue('notifications')
            ->finally(static function () use ($notificationId): void {
                AdminNotification::query()->whereKey($notificationId)->update([
                    'status' => AdminNotification::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
            })
            ->dispatch();

        $notification->update(['batch_id' => $batch->id]);
    }
}
