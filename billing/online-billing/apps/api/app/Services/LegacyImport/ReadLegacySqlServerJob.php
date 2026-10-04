<?php

namespace App\Services\LegacyImport;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ReadLegacySqlServerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public bool $failOnTimeout = true;

    public function __construct(public int $readId)
    {
        $this->onConnection('legacy_import');
        $this->onQueue('legacy-imports');
    }

    public function handle(LegacySqlImportService $imports): void
    {
        $imports->process($this->readId);
    }

    public function failed(?Throwable $exception): void
    {
        app(LegacySqlImportService::class)->fail($this->readId);
    }
}
