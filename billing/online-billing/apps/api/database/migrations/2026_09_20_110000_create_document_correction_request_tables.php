<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_correction_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->onDelete('restrict');
            $table->foreignId('receipt_id')->nullable()->constrained('receipts')->onDelete('restrict');
            $table->string('requested_action', 32); // INVOICE_CORRECTION, RECEIPT_REVERSAL
            $table->string('status', 32)->default('PENDING'); // PENDING, APPROVED, REJECTED, CANCELLED, EXECUTED
            $table->unsignedInteger('target_lock_version');
            $table->foreignId('target_revision_id')->constrained('document_revisions')->onDelete('restrict');
            $table->string('target_snapshot_hash', 64);
            $table->text('reason');
            $table->foreignId('requested_by_user_id')->constrained('users')->onDelete('restrict');
            $table->timestampTz('requested_at');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestampsTz();

            $table->index(['organization_id', 'status', 'requested_at']);
            $table->index(['invoice_id', 'status']);
            $table->index(['receipt_id', 'status']);
        });
        DB::statement('ALTER TABLE document_correction_requests ADD CONSTRAINT correction_request_exactly_one_target CHECK ((invoice_id IS NOT NULL AND receipt_id IS NULL) OR (invoice_id IS NULL AND receipt_id IS NOT NULL))');
        DB::statement('ALTER TABLE document_correction_requests ADD CONSTRAINT correction_request_distinct_reviewer CHECK (reviewed_by_user_id IS NULL OR reviewed_by_user_id <> requested_by_user_id)');
        DB::statement("CREATE UNIQUE INDEX correction_request_one_active_invoice_action ON document_correction_requests (invoice_id, requested_action) WHERE status IN ('PENDING', 'APPROVED')");
        DB::statement("CREATE UNIQUE INDEX correction_request_one_active_receipt_action ON document_correction_requests (receipt_id, requested_action) WHERE status IN ('PENDING', 'APPROVED')");

        Schema::create('document_correction_request_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('correction_request_id')->constrained('document_correction_requests')->onDelete('restrict');
            $table->foreignId('actor_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->string('event_type', 32); // REQUESTED, APPROVED, REJECTED, CANCELLED, EXECUTED
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['correction_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_correction_request_events');
        Schema::dropIfExists('document_correction_requests');
    }
};
