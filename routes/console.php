<?php

use App\Domain\Otp\Services\OtpService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('realtime:prune-expired-subscriptions')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::call(fn (OtpService $otp) => $otp->cleanup())
    ->hourly()
    ->name('otp-cleanup');
