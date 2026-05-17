<?php

use Illuminate\Support\Facades\Schedule;
use Veloquent\Core\Domain\Otp\Services\OtpService;

Schedule::command('realtime:prune-expired-subscriptions')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::call(fn (OtpService $otp) => $otp->cleanup())
    ->hourly()
    ->name('otp-cleanup');
