<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Travels\Models\TravelBooking;
use Modules\Travels\Services\TravelService;

class ReleaseExpiredTravelBookings extends Command
{
    protected $signature = 'travels:release-expired-bookings';

    protected $description = 'Release travel seats locked by expired reservations.';

    public function __construct(
        private readonly TravelService $travelService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $releasedCount = 0;

        $expiredBookings = TravelBooking::query()
            ->where('status', TravelBooking::STATUS_PENDING_PAYMENT)
            ->where('created_at', '<=', now()->subMinutes($this->travelService->reservationTimeoutMinutes()))
            ->get();

        foreach ($expiredBookings as $travelBooking) {
            if ($this->travelService->expireReservation($travelBooking)) {
                $releasedCount++;
            }
        }

        $this->info(sprintf('Released %d expired travel booking(s).', $releasedCount));

        return self::SUCCESS;
    }
}
