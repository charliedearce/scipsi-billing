<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Customer Creditable Withholding Tax Certificates (BIR Form 2307)
        Schema::create('customer_withholding_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('certificate_no', 64);
            $table->foreignId('private_file_id')->constrained('private_files')->cascadeOnDelete();
            $table->unsignedInteger('reviewed_version_number');
            $table->string('payor_tin', 32);
            $table->string('payor_name', 255);
            $table->string('payee_tin', 32);
            $table->string('payee_name', 255);
            $table->date('period_from');
            $table->date('period_to');
            $table->string('tax_type', 32)->default('CREDITABLE_WITHHOLDING_TAX');
            $table->string('atc_code', 16); // e.g. WC100, WC157
            $table->decimal('income_payment_base', 14, 2);
            $table->decimal('withholding_rate', 6, 4); // e.g. 0.0100, 0.0200
            $table->decimal('certified_amount', 14, 2);
            $table->decimal('allocated_amount', 14, 2)->default(0.00);
            $table->decimal('remaining_amount', 14, 2);
            $table->string('status', 32)->default('PENDING_REVIEW'); // PENDING_REVIEW, APPROVED, NEEDS_CORRECTION, REJECTED, EXPIRED, REVOKED

            // Staff Review & Audit
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('decision_notes')->nullable();
            $table->text('customer_notes')->nullable();

            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();

            $table->unique(['organization_id', 'certificate_no'], 'cwt_certs_org_cert_unique');
            $table->index(['customer_id', 'status'], 'cwt_certs_customer_status_idx');
            $table->index(['organization_id', 'status', 'period_from', 'period_to'], 'cwt_certs_period_idx');
        });

        // 2. Customer Tax Exemptions & Zero-Rating (PEZA, BOI, Diplomatic, NIRC Sec 109 / 108(B))
        Schema::create('customer_tax_exemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('exemption_type', 32); // VAT_EXEMPT, ZERO_RATED
            $table->string('legal_basis', 255); // e.g. PEZA Reg No., NIRC Sec. 109
            $table->string('ruling_or_cert_no', 64);
            $table->jsonb('covered_services')->default('["ALL"]');
            $table->date('valid_from');
            $table->date('valid_to')->nullable(); // Nullable for perpetual or open-ended rulings
            $table->foreignId('private_file_id')->constrained('private_files')->cascadeOnDelete();
            $table->unsignedInteger('reviewed_version_number');
            $table->string('status', 32)->default('PENDING_REVIEW'); // PENDING_REVIEW, APPROVED, NEEDS_CORRECTION, REJECTED, EXPIRED, REVOKED

            // Staff Review & Audit
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('decision_notes')->nullable();
            $table->text('customer_notes')->nullable();

            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();

            $table->index(['customer_id', 'status', 'valid_from', 'valid_to'], 'tax_exemptions_cust_validity_idx');
            $table->index(['organization_id', 'exemption_type', 'status'], 'tax_exemptions_org_type_idx');
        });

        // 3. Tax Evidence Audit Events
        Schema::create('customer_tax_evidence_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('evidence_type', 32); // WITHHOLDING_CERTIFICATE, TAX_EXEMPTION
            $table->unsignedBigInteger('evidence_id');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 64); // SUBMITTED, APPROVED, CORRECTION_REQUESTED, RESUBMITTED, REJECTED, REVOKED
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->default('[]');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['evidence_type', 'evidence_id'], 'tax_evidence_events_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_tax_evidence_events');
        Schema::dropIfExists('customer_tax_exemptions');
        Schema::dropIfExists('customer_withholding_certificates');
    }
};
