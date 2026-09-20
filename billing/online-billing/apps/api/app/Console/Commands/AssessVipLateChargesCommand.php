<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Billing\LateChargeAssessmentService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Idempotent Asia/Manila VIP late-charge assessment runner (P3-11 / W30).
 * Creates separate principal-only assessments; never issues fiscal documents.
 */
class AssessVipLateChargesCommand extends Command
{
    protected $signature = 'credit:assess-late-charges
        {--organization= : Limit to one organization id}
        {--as-of= : Asia/Manila business date YYYY-MM-DD (defaults to today)}';

    protected $description = 'Assess non-compounding VIP late charges against unpaid principal for eligible captured policies';

    public function handle(LateChargeAssessmentService $assessments): int
    {
        $asOf = $this->option('as-of')
            ? Carbon::parse((string) $this->option('as-of'), LateChargeAssessmentService::TIMEZONE)->startOfDay()
            : Carbon::now(LateChargeAssessmentService::TIMEZONE)->startOfDay();

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
            $result = $assessments->assessOrganization((int) $organization->id, $asOf, null);
            $this->info(sprintf(
                'Organization %d @ %s: created=%d reused=%d held=%d skipped=%d',
                $organization->id,
                $result['as_of'],
                $result['created'],
                $result['reused'],
                $result['held'],
                $result['skipped'],
            ));
        }

        return self::SUCCESS;
    }
}
