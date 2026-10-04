<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Billing\TaxEvidenceExpiryService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Asia/Manila tax-evidence expiry + approaching-renewal notifier (P2-08 / W20 / W31).
 * Marks APPROVED evidence EXPIRED after period_to/valid_to; durable in-app TAX alerts;
 * SMS only for expired outcomes already covered by W31.
 */
class ProcessTaxEvidenceExpiryCommand extends Command
{
    protected $signature = 'tax:process-evidence-expiry
        {--organization= : Limit to one organization id}
        {--as-of= : Asia/Manila business date YYYY-MM-DD (defaults to today)}';

    protected $description = 'Expire due tax evidence and notify customers approaching or past expiry';

    public function handle(TaxEvidenceExpiryService $expiry): int
    {
        $asOf = $this->option('as-of')
            ? Carbon::parse((string) $this->option('as-of'), TaxEvidenceExpiryService::TIMEZONE)->startOfDay()
            : Carbon::now(TaxEvidenceExpiryService::TIMEZONE)->startOfDay();

        $query = Organization::query()->orderBy('id');
        if ($this->option('organization')) {
            $query->whereKey((int) $this->option('organization'));
        }

        $organizations = $query->get();
        if ($organizations->isEmpty()) {
            $this->warn('No organizations matched.');

            return self::SUCCESS;
        }

        foreach ($organizations as $organization) {
            $result = $expiry->processOrganization((int) $organization->id, $asOf);
            $this->info(sprintf(
                'Organization %d @ %s: expired_wht=%d expired_ex=%d approaching_wht=%d approaching_ex=%d notifications=%d',
                $organization->id,
                $result['as_of'],
                $result['expired_withholding'],
                $result['expired_exemptions'],
                $result['approaching_withholding'],
                $result['approaching_exemptions'],
                $result['notifications_created'],
            ));
        }

        return self::SUCCESS;
    }
}
