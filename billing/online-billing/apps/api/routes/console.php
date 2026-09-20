<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// P3-11: idempotent VIP late-charge assessment. Safe to retry; never issues fiscal documents.
Schedule::command('credit:assess-late-charges')
    ->dailyAt('01:15')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();
