<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Ads\Models\AdRequest;
use Modules\Ads\Services\AdRequestService;

class ReleaseExpiredAdSpaceReservations extends Command
{
    protected $signature = 'ads:release-expired-reservations';

    protected $description = 'Release ad spaces locked by expired reservations and finished paid bookings.';

    public function __construct(
        private readonly AdRequestService $adRequestService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $expiredReservations = AdRequest::query()
            ->where('status', 'pending_payment')
            ->where('created_at', '<=', now()->subMinutes($this->adRequestService->reservationTimeoutMinutes()))
            ->get();
        $finishedReservations = AdRequest::query()
            ->whereIn('status', ['paid_successfully', 'approved'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->get();

        $releasedCount = 0;

        foreach ($expiredReservations as $adRequest) {
            if ($this->adRequestService->expireReservation($adRequest)) {
                $releasedCount++;
            }
        }

        foreach ($finishedReservations as $adRequest) {
            if ($this->adRequestService->completeFinishedReservation($adRequest)) {
                $releasedCount++;
            }
        }

        $this->info(sprintf('Released %d expired ad space reservation(s).', $releasedCount));

        return self::SUCCESS;
    }
}
