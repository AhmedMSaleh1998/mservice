<?php

namespace Modules\Notifications\Services;

use App\Jobs\DispatchAdminNotification;
use App\Support\NationalIdentifier;
use Illuminate\Database\Eloquent\Builder;
use Modules\Notifications\Models\AdminNotification;
use Modules\Users\Models\User;

class AdminNotificationService
{
    /**
     * Active users the notification goes to, according to its audience.
     */
    public function recipientsQuery(AdminNotification $notification): Builder
    {
        return User::query()
            ->where('active', true)
            ->when(
                $notification->audience === AdminNotification::AUDIENCE_USERS,
                fn (Builder $query) => $query->whereIn('id', $notification->user_ids ?? []),
            )
            ->when(
                $notification->audience === AdminNotification::AUDIENCE_REGISTRATION_NUMBERS,
                fn (Builder $query) => $query->whereIn('reg_number', $notification->registration_numbers ?? []),
            );
    }

    public function countRecipients(AdminNotification $notification): int
    {
        return $this->recipientsQuery($notification)->count();
    }

    /**
     * Registration numbers pasted by the admin, one per line or separated by
     * commas or spaces, in Arabic or Latin digits.
     *
     * @return array<int, string>
     */
    public function parseRegistrationNumbers(?string $input): array
    {
        $numbers = preg_split('/[\s,،]+/u', (string) $input, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(array_map(
            static fn (string $number): string => NationalIdentifier::normalize($number),
            $numbers,
        ))));
    }

    public function send(AdminNotification $notification): void
    {
        if (! $notification->isDraft()) {
            return;
        }

        $notification->update([
            'status' => AdminNotification::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        DispatchAdminNotification::dispatch($notification->id);
    }
}
