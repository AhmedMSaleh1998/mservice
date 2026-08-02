<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Courses\Models\CourseBooking;
use Modules\Courses\Services\CourseBookingService;

class ReleaseExpiredCourseBookings extends Command
{
    protected $signature = 'courses:release-expired-bookings';

    protected $description = 'Release course seats locked by expired reservations.';

    public function __construct(
        private readonly CourseBookingService $courseBookingService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $expiredBookings = CourseBooking::query()
            ->where('status', 'pending_payment')
            ->where('created_at', '<=', now()->subMinutes($this->courseBookingService->reservationTimeoutMinutes()))
            ->get();

        $releasedCount = 0;

        foreach ($expiredBookings as $courseBooking) {
            if ($this->courseBookingService->expireReservation($courseBooking)) {
                $releasedCount++;
            }
        }

        $this->info(sprintf('Released %d expired course booking(s).', $releasedCount));

        return self::SUCCESS;
    }
}
