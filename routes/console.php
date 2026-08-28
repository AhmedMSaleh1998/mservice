<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Ads\Models\AdRequest;
use Modules\Core\Models\Order;
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

// Keeps user names aligned with the syndicate's official Oracle records: each
// user is re-verified by registration number + national ID, then the name is
// refreshed from Oracle. Runs at night after the medical-guide sync; the full
// pass makes two Oracle calls per user, so give it a generous overlap lock.
Schedule::command('users:sync-oracle-names --apply')
    ->dailyAt('04:00')
    ->withoutOverlapping(expiresAt: 180);

// Safety net for the Fawry webhook/return redirect: pulls the gateway status for
// pending checkouts (applying missed payments, which also triggers the Oracle
// payment sync) and expires checkouts whose payment window has passed. Only
// spawns when pending Fawry checkouts actually exist.
Schedule::command('payments:reconcile-fawry')
    ->everyTwoMinutes()
    ->withoutOverlapping()
    ->when(fn (): bool => Order::query()
        ->where('payment_method', 'fawry')
        ->where('status', 'checkout_pending')
        ->whereNotNull('merchant_ref_num')
        ->exists());

// Membership/certificate orders abandoned before ever reaching the gateway
// (no merchant reference) get a terminal state after two days — they hold no
// inventory, so unlike the booking release commands a daily pass is enough.
Schedule::command('payments:expire-abandoned-orders')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->when(fn (): bool => Order::query()
        ->where('status', 'pending_payment')
        ->whereNull('merchant_ref_num')
        ->where('created_at', '<=', now()->subDays(2))
        ->exists());
