<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Services\Models\RestUnitBooking;
use Modules\Services\Services\RestUnitService;

class ReleaseExpiredRestUnitBookings extends Command
{
    protected $signature = 'rest-units:release-expired-bookings';

    protected $description = 'Release rest unit rooms locked by expired reservations.';

    public function __construct(
        private readonly RestUnitService $restUnitService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $expiredBookings = RestUnitBooking::query()
            ->where('status', RestUnitBooking::STATUS_PENDING_PAYMENT)
            ->where('created_at', '<=', now()->subMinutes($this->restUnitService->reservationTimeoutMinutes()))
            ->get();

        $releasedCount = 0;

        foreach ($expiredBookings as $restUnitBooking) {
            if ($this->restUnitService->expireReservation($restUnitBooking)) {
                $releasedCount++;
            }
        }

        $this->info(sprintf('Released %d expired rest unit booking(s).', $releasedCount));

        return self::SUCCESS;
    }
}
