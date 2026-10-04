<?php

use App\Services\LegacyImport\LegacySqlImportService;
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

// P2-08: expire due tax evidence and notify approaching (<30 days) / expired renewals.
Schedule::command('tax:process-evidence-expiry')
    ->dailyAt('01:30')
    ->timezone('Asia/Manila')
    ->withoutOverlapping();

Artisan::command('legacy-imports:expire', function (LegacySqlImportService $imports): void {
    $this->info('Expired source reads: '.$imports->expire());
})->purpose('Clear expired temporary SQL import credentials and partial snapshots');

Schedule::command('legacy-imports:expire')->everyFiveMinutes()->withoutOverlapping();
