<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-11 / W30: versioned VIP late-charge policies and a separate assessment ledger.
 * Assessments never rewrite invoice due dates, tariff/tax totals or collection receipts.
 * Fiscal document/series/GL mapping remains accountant-gated and is not created here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('late_charge_policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->unsignedInteger('version_number');
            $table->string('currency', 3)->default('PHP');
            $table->boolean('enabled')->default(true);
            $table->boolean('allow_customer_overrides')->default(false);
            $table->unsignedInteger('grace_days')->default(0);
            // FIXED or PERCENTAGE applied to unpaid principal only (non-compounding baseline).
            $table->string('basis', 16);
            $table->decimal('fixed_amount', 14, 2)->nullable();
            $table->decimal('percentage_rate', 8, 4)->nullable();
            $table->string('cadence', 16); // ONCE or MONTHLY
            $table->decimal('minimum_amount', 14, 2)->nullable();
            $table->decimal('cap_amount', 14, 2)->nullable();
            $table->string('rounding_mode', 24)->default('TRUNCATE_2');
            // PRINCIPAL_FIRST is the only approved allocation priority until accountant review.
            $table->string('allocation_priority', 32)->default('PRINCIPAL_FIRST');
            $table->boolean('affects_available_credit')->default(false);
            $table->string('contract_reference', 128)->nullable();
            $table->text('customer_notice')->nullable();
            $table->string('status', 16)->default('DRAFT');
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestampTz('published_at')->nullable();
            $table->text('publication_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();

            $table->unique(['organization_id', 'version_number'], 'late_charge_policy_version_unique');
            $table->index(['organization_id', 'status', 'effective_from'], 'late_charge_policy_effective_index');
        });

        Schema::create('late_charge_bands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('late_charge_policy_version_id')->constrained('late_charge_policy_versions')->onDelete('restrict');
            $table->unsignedInteger('days_from');
            $table->unsignedInteger('days_to')->nullable(); // null = open upper bound
            $table->string('label', 64);
            $table->decimal('fixed_amount_override', 14, 2)->nullable();
            $table->decimal('percentage_rate_override', 8, 4)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['late_charge_policy_version_id', 'days_from'], 'late_charge_band_range_index');
        });

        Schema::table('invoice_credit_charges', function (Blueprint $table): void {
            // Captured only for future charges when a published late-charge policy is effective.
            // Null means the charge is not late-charge eligible; never backfill older debt.
            $table->foreignId('late_charge_policy_version_id')->nullable()->after('credit_account_version_number')
                ->constrained('late_charge_policy_versions')->onDelete('restrict');
            $table->unsignedInteger('late_charge_policy_version_number')->nullable()->after('late_charge_policy_version_id');
            $table->jsonb('late_charge_policy_snapshot')->nullable()->after('late_charge_policy_version_number');
        });

        Schema::create('late_charge_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->foreignId('customer_credit_account_id')->constrained('customer_credit_accounts')->onDelete('restrict');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('invoice_credit_charge_id')->constrained('invoice_credit_charges')->onDelete('restrict');
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('restrict');
            $table->foreignId('late_charge_policy_version_id')->constrained('late_charge_policy_versions')->onDelete('restrict');
            $table->unsignedInteger('late_charge_policy_version_number');
            $table->foreignId('late_charge_band_id')->nullable()->constrained('late_charge_bands')->onDelete('restrict');
            $table->string('currency', 3);
            $table->date('as_of_date');
            $table->unsignedInteger('days_past_due');
            $table->string('cycle_key', 64);
            $table->decimal('principal_outstanding', 14, 2);
            $table->string('basis', 16);
            $table->decimal('rate_or_amount', 14, 4);
            $table->string('rounding_mode', 24);
            $table->decimal('assessed_amount', 14, 2);
            $table->string('status', 32); // CANDIDATE, ON_HOLD, POSTED, VOIDED, WAIVED, REVERSED, RECONCILIATION_REQUIRED
            $table->string('hold_reason', 128)->nullable();
            $table->string('fiscal_mapping_status', 48)->default('PENDING_ACCOUNTANT_REVIEW');
            $table->jsonb('calculation_snapshot');
            $table->timestampTz('assessed_at');
            $table->foreignId('assessed_by_user_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestampTz('posted_at')->nullable();
            $table->timestampTz('waived_at')->nullable();
            $table->foreignId('waived_by_user_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->text('waiver_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();

            $table->unique(
                ['invoice_credit_charge_id', 'late_charge_policy_version_id', 'cycle_key'],
                'late_charge_assessment_cycle_unique'
            );
            $table->index(['organization_id', 'status', 'as_of_date'], 'late_charge_assessment_status_index');
            $table->index(['customer_credit_account_id', 'status'], 'late_charge_assessment_account_index');
            $table->index(['invoice_id', 'status'], 'late_charge_assessment_invoice_index');
        });

        Schema::create('late_charge_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('late_charge_assessment_id')->constrained('late_charge_assessments')->onDelete('restrict');
            $table->foreignId('actor_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->string('event_type', 48);
            $table->text('reason')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['late_charge_assessment_id', 'created_at'], 'late_charge_event_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('late_charge_events');
        Schema::dropIfExists('late_charge_assessments');

        Schema::table('invoice_credit_charges', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('late_charge_policy_version_id');
            $table->dropColumn(['late_charge_policy_version_number', 'late_charge_policy_snapshot']);
        });

        Schema::dropIfExists('late_charge_bands');
        Schema::dropIfExists('late_charge_policy_versions');
    }
};
