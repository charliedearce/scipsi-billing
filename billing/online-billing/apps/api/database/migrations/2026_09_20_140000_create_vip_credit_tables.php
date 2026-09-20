<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->unsignedInteger('version_number');
            $table->string('currency', 3)->default('PHP');
            $table->string('default_credit_limit_mode', 16); // CAPPED or UNLIMITED; null never means unlimited.
            $table->decimal('default_credit_limit_amount', 14, 2)->nullable();
            $table->unsignedInteger('payment_terms_days');
            $table->string('due_date_basis', 24); // INVOICE_DATE or CREDIT_CHARGE_DATE
            $table->string('overdue_restriction', 16); // ALLOW, WARN or BLOCK
            $table->unsignedInteger('overdue_grace_days')->default(0);
            $table->decimal('overdue_amount_threshold', 14, 2)->nullable();
            $table->boolean('allow_customer_overrides')->default(false);
            $table->string('status', 16)->default('DRAFT');
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestampTz('published_at')->nullable();
            $table->text('publication_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();

            $table->unique(['organization_id', 'version_number'], 'credit_policy_version_unique');
            $table->index(['organization_id', 'status', 'effective_from'], 'credit_policy_effective_index');
        });

        Schema::create('customer_credit_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('restrict');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();

            $table->unique(['organization_id', 'customer_id'], 'customer_credit_account_unique');
        });

        // Customer overrides are immutable published versions. An override field is null only when
        // the effective organization policy supplies that value; it never means an unlimited limit.
        Schema::create('customer_credit_account_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_credit_account_id')->constrained('customer_credit_accounts')->onDelete('restrict');
            $table->unsignedInteger('version_number');
            $table->string('status', 16); // ACTIVE, HELD, DISABLED
            $table->string('credit_limit_mode_override', 16)->nullable();
            $table->decimal('credit_limit_amount_override', 14, 2)->nullable();
            $table->unsignedInteger('payment_terms_days_override')->nullable();
            $table->string('due_date_basis_override', 24)->nullable();
            $table->string('overdue_restriction_override', 16)->nullable();
            $table->unsignedInteger('overdue_grace_days_override')->nullable();
            $table->decimal('overdue_amount_threshold_override', 14, 2)->nullable();
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('restrict');
            $table->text('reason');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();

            $table->unique(['customer_credit_account_id', 'version_number'], 'credit_account_version_unique');
            $table->index(['customer_credit_account_id', 'effective_from'], 'credit_account_effective_index');
        });

        // The original invoice remains the receivable. A charge records only its credit terms and
        // exposure assignment; it never creates a duplicate financial header or collection receipt.
        Schema::create('invoice_credit_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->foreignId('customer_credit_account_id')->constrained('customer_credit_accounts')->onDelete('restrict');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('restrict');
            $table->foreignId('credit_policy_version_id')->constrained('credit_policy_versions')->onDelete('restrict');
            $table->unsignedInteger('credit_policy_version_number');
            $table->foreignId('credit_account_version_id')->constrained('customer_credit_account_versions')->onDelete('restrict');
            $table->unsignedInteger('credit_account_version_number');
            $table->string('currency', 3);
            $table->decimal('charged_amount', 14, 2);
            $table->unsignedInteger('payment_terms_days_snapshot');
            $table->string('due_date_basis_snapshot', 24);
            $table->date('due_date');
            $table->jsonb('terms_snapshot');
            $table->timestampTz('charged_at');
            $table->foreignId('charged_by_user_id')->constrained('users')->onDelete('restrict');
            $table->timestampsTz();

            $table->unique('invoice_id', 'invoice_credit_charge_invoice_unique');
            $table->index(['customer_credit_account_id', 'currency', 'due_date'], 'credit_charge_account_due_index');
            $table->index(['organization_id', 'customer_id', 'charged_at'], 'credit_charge_customer_index');
        });

        Schema::create('vip_credit_repayment_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->foreignId('customer_credit_account_id')->constrained('customer_credit_accounts')->onDelete('restrict');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('proof_file_id')->constrained('private_files')->onDelete('restrict');
            $table->foreignId('receipt_id')->nullable()->unique()->constrained('receipts')->onDelete('restrict');
            $table->foreignId('submitted_by_user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->string('source_key', 128);
            $table->string('status', 32)->default('SUBMITTED'); // SUBMITTED, IN_REVIEW, REJECTED, APPROVED
            $table->string('currency', 3);
            $table->decimal('requested_amount', 14, 2);
            $table->string('declared_reference', 128)->nullable();
            $table->string('confirmed_reference', 128)->nullable();
            $table->timestampTz('initial_submitted_at');
            $table->timestampTz('submitted_at');
            $table->timestampTz('assigned_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('resubmission_rounds')->default(0);
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('approval_payload_fingerprint', 64)->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'source_key'], 'vip_credit_repayment_source_unique');
            $table->index(['organization_id', 'status', 'initial_submitted_at'], 'vip_credit_repayment_queue_index');
            $table->index(['customer_credit_account_id', 'status'], 'vip_credit_repayment_account_index');
        });

        Schema::create('vip_credit_repayment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vip_credit_repayment_submission_id')->constrained('vip_credit_repayment_submissions')->onDelete('restrict');
            $table->foreignId('invoice_credit_charge_id')->constrained('invoice_credit_charges')->onDelete('restrict');
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('restrict');
            $table->unsignedInteger('expected_invoice_lock_version');
            $table->decimal('requested_amount', 14, 2);
            $table->timestampsTz();

            $table->unique(['vip_credit_repayment_submission_id', 'invoice_id'], 'vip_credit_repayment_allocation_unique');
            $table->index(['invoice_id', 'created_at'], 'vip_credit_repayment_invoice_index');
        });

        Schema::create('vip_credit_repayment_proofs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vip_credit_repayment_submission_id')->constrained('vip_credit_repayment_submissions')->onDelete('restrict');
            $table->foreignId('private_file_id')->constrained('private_files')->onDelete('restrict');
            $table->unsignedInteger('private_file_version_number');
            $table->unsignedInteger('attempt_number');
            $table->foreignId('submitted_by_user_id')->constrained('users')->onDelete('restrict');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['vip_credit_repayment_submission_id', 'attempt_number'], 'vip_credit_repayment_proof_attempt_unique');
            $table->unique(['private_file_id', 'private_file_version_number'], 'vip_credit_repayment_proof_file_version_unique');
        });

        // A confirmed bank reference is an organization/currency-scoped settlement identity. It is
        // not a proof-image hash and cannot be reused to create another receipt.
        Schema::create('bank_transfer_settlement_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->string('currency', 3);
            $table->string('normalized_reference', 128);
            $table->string('source_type', 48);
            $table->string('source_key', 128);
            $table->foreignId('receipt_id')->nullable()->unique()->constrained('receipts')->onDelete('restrict');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['organization_id', 'currency', 'normalized_reference'], 'bank_transfer_reference_unique');
        });

        Schema::create('credit_account_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_credit_account_id')->constrained('customer_credit_accounts')->onDelete('restrict');
            $table->foreignId('invoice_credit_charge_id')->nullable()->constrained('invoice_credit_charges')->onDelete('restrict');
            $table->foreignId('vip_credit_repayment_submission_id')->nullable()->constrained('vip_credit_repayment_submissions')->onDelete('restrict');
            $table->foreignId('receipt_id')->nullable()->constrained('receipts')->onDelete('restrict');
            $table->foreignId('actor_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->string('event_type', 48);
            $table->text('reason')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['customer_credit_account_id', 'created_at'], 'credit_account_event_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_account_events');
        Schema::dropIfExists('bank_transfer_settlement_references');
        Schema::dropIfExists('vip_credit_repayment_proofs');
        Schema::dropIfExists('vip_credit_repayment_allocations');
        Schema::dropIfExists('vip_credit_repayment_submissions');
        Schema::dropIfExists('invoice_credit_charges');
        Schema::dropIfExists('customer_credit_account_versions');
        Schema::dropIfExists('customer_credit_accounts');
        Schema::dropIfExists('credit_policy_versions');
    }
};
