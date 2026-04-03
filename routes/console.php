<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('ads:release-expired-reservations')->everyMinute();
Schedule::command('courses:release-expired-bookings')->everyMinute();
Schedule::command('rest-units:release-expired-bookings')->everyMinute();
