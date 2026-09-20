<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A durable idempotency/locking row exists before a receipt number is consumed.
        Schema::create('receipt_posting_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->string('source_type', 32);
            $table->string('source_key', 128);
            $table->string('payload_fingerprint', 64);
            $table->foreignId('receipt_id')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'source_type', 'source_key'], 'receipt_source_idempotency_unique');
        });

        Schema::create('receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->foreignId('location_id')->nullable()->constrained('locations')->onDelete('restrict');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('series_id')->constrained('document_series')->onDelete('restrict');
            $table->foreignId('posting_source_id')->nullable()->unique()->constrained('receipt_posting_sources')->onDelete('restrict');
            $table->string('receipt_number', 64)->nullable();
            $table->string('status', 32)->default('POSTED');
            $table->date('business_date');
            $table->string('currency', 3)->default('PHP');
            $table->jsonb('payer_snapshot');
            $table->decimal('cash_received_amount', 14, 2)->default(0);
            $table->decimal('withholding_received_amount', 14, 2)->default(0);
            $table->decimal('applied_amount', 14, 2)->default(0);
            $table->decimal('unapplied_amount', 14, 2)->default(0);
            $table->unsignedInteger('lock_version')->default(1);
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestampTz('posted_at');
            $table->timestampsTz();

            $table->unique(['organization_id', 'receipt_number'], 'receipts_org_number_unique');
            $table->index(['organization_id', 'customer_id', 'posted_at']);
        });

        Schema::table('receipt_posting_sources', function (Blueprint $table): void {
            $table->foreign('receipt_id')->references('id')->on('receipts')->onDelete('restrict');
        });

        Schema::create('receipt_tenders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts')->onDelete('restrict');
            $table->string('tender_type', 32);
            $table->string('status', 32);
            $table->decimal('amount', 14, 2);
            $table->string('reference', 128)->nullable();
            $table->jsonb('tender_snapshot')->nullable();
            $table->timestampsTz();

            $table->index(['receipt_id', 'tender_type']);
        });

        Schema::create('receipt_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts')->onDelete('restrict');
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('restrict');
            $table->decimal('cash_applied_amount', 14, 2)->default(0);
            $table->decimal('withholding_applied_amount', 14, 2)->default(0);
            $table->decimal('applied_amount', 14, 2);
            $table->timestampsTz();

            $table->unique(['receipt_id', 'invoice_id']);
            $table->index(['invoice_id', 'created_at']);
        });

        Schema::create('withholding_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts')->onDelete('restrict');
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('restrict');
            $table->foreignId('certificate_id')->constrained('customer_withholding_certificates')->onDelete('restrict');
            $table->decimal('applied_amount', 14, 2);
            $table->timestampsTz();

            $table->unique(['receipt_id', 'invoice_id', 'certificate_id'], 'receipt_withholding_application_unique');
            $table->index(['certificate_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withholding_applications');
        Schema::dropIfExists('receipt_allocations');
        Schema::dropIfExists('receipt_tenders');
        Schema::table('receipt_posting_sources', function (Blueprint $table): void {
            $table->dropForeign(['receipt_id']);
        });
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('receipt_posting_sources');
    }
};
