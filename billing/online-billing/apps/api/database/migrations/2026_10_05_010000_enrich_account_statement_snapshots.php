<?php

use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateActivation;
use App\Models\User;
use App\Services\DocumentStudio\DocumentStudioService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_statements', function (Blueprint $table): void {
            $table->decimal('cash_applied_total', 14, 2)->default(0)->after('payment_total');
            $table->decimal('withholding_applied_total', 14, 2)->default(0)->after('cash_applied_total');
        });

        $studio = app(DocumentStudioService::class);
        $layout = $studio->getDefaultAccountStatementLayout();

        foreach (DocumentTemplate::query()->where('code', 'SOA-DEFAULT')->get() as $template) {
            if ($this->activeLayoutSplitsCollections($template)) {
                continue;
            }

            $actor = User::query()
                ->where('organization_id', $template->organization_id)
                ->whereHas('roles', fn ($query) => $query->where('name', 'Administrator'))
                ->orderBy('id')
                ->first();

            if (! $actor) {
                throw new RuntimeException("SOA-DEFAULT for organization {$template->organization_id} has no administrator to publish the richer statement layout.");
            }

            $draft = $studio->createDraftVersion($template, $actor, $layout);
            $published = $studio->publishVersion($draft, $actor);
            $studio->activateVersion($published, $actor);
        }
    }

    public function down(): void
    {
        Schema::table('account_statements', function (Blueprint $table): void {
            $table->dropColumn(['cash_applied_total', 'withholding_applied_total']);
        });
    }

    private function activeLayoutSplitsCollections(DocumentTemplate $template): bool
    {
        $activation = DocumentTemplateActivation::query()
            ->where('organization_id', $template->organization_id)
            ->where('document_kind', 'ACCOUNT_STATEMENT')
            ->where('is_active', true)
            ->whereNull('location_id')
            ->whereNull('series_id')
            ->first();
        $columns = $activation?->templateVersion?->layout_definition['bands']['details']['elements'][0]['columns'] ?? [];

        foreach ($columns as $column) {
            if (($column['field'] ?? null) === 'cash_applied_amount') {
                return true;
            }
        }

        return false;
    }
};
