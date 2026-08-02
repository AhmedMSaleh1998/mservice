<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Ads\Models\AdRequest;
use Modules\Courses\Models\CourseBooking;
use Modules\Services\Models\RestUnitBooking;
use Modules\Travels\Models\TravelBooking;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Each release command only launches when its queue actually has rows to process,
// so no command process is spawned while there is nothing pending. The guard runs a
// cheap indexed EXISTS query inside the scheduler process itself.
Schedule::command('ads:release-expired-reservations')
    ->everyMinute()
    ->when(fn (): bool => AdRequest::query()->where('status', 'pending_payment')->exists()
        || AdRequest::query()
            ->whereIn('status', ['paid_successfully', 'approved'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->exists());

Schedule::command('courses:release-expired-bookings')
    ->everyMinute()
    ->when(fn (): bool => CourseBooking::query()->where('status', 'pending_payment')->exists());

Schedule::command('rest-units:release-expired-bookings')
    ->everyMinute()
    ->when(fn (): bool => RestUnitBooking::query()->where('status', RestUnitBooking::STATUS_PENDING_PAYMENT)->exists());

Schedule::command('travels:release-expired-bookings')
    ->everyMinute()
    ->when(fn (): bool => TravelBooking::query()->where('status', TravelBooking::STATUS_PENDING_PAYMENT)->exists());

Schedule::command('medical-guides:sync-oracle')->dailyAt('03:00')->withoutOverlapping();
