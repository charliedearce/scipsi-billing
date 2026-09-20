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
        // 1. Queue Tickets Sequence Tracker (Per org and location)
        Schema::create('queue_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->unsignedInteger('last_ticket_number')->default(1000);
            $table->timestampsTz();

            $table->unique(['organization_id', 'location_id'], 'queue_tickets_org_loc_unique');
        });

        // 2. Billing Requests
        Schema::create('billing_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('service_type', 64);
            $table->string('transaction_no', 64);
            $table->unsignedInteger('ticket_number')->nullable();
            $table->string('status', 32)->default('DRAFT'); // DRAFT, QUEUED, IN_REVIEW, NEEDS_CORRECTION, BILLING_IN_PROGRESS, BILL_READY, CANCELLED, CLOSED

            // Queue Fairness Timestamps & Order Invariants
            $table->timestampTz('initial_submitted_at')->nullable(); // Immutable once set on first submission (Decision W22)
            $table->timestampTz('submitted_at')->nullable(); // Current submission/resubmission timestamp
            $table->timestampTz('admitted_at')->nullable(); // Timestamp when automatically admitted to queue

            // Teller Assignment & Heartbeat Lease
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('assigned_at')->nullable();
            $table->timestampTz('assignment_heartbeat_at')->nullable();

            // Correction Rounds & Notes
            $table->unsignedInteger('correction_rounds')->default(0);
            $table->text('correction_notes')->nullable(); // Customer-facing explanation
            $table->text('internal_notes')->nullable(); // Staff internal notes

            // Invoices link
            $table->foreignId('draft_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            // Requirement versioning snapshot & Concurrency
            $table->jsonb('requirement_snapshot')->default('[]');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();

            $table->unique(['organization_id', 'transaction_no'], 'billing_requests_org_txn_unique');
            $table->index(
                ['organization_id', 'location_id', 'status', 'initial_submitted_at', 'ticket_number'],
                'billing_requests_queue_claim_idx'
            );
            $table->index(['customer_id', 'status'], 'billing_requests_customer_status_idx');
            $table->index(['assigned_to_user_id', 'status'], 'billing_requests_teller_status_idx');
        });

        // 3. Billing Request Documents
        Schema::create('billing_request_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_request_id')->constrained('billing_requests')->cascadeOnDelete();
            $table->foreignId('document_requirement_id')->nullable()->constrained('document_requirements')->nullOnDelete();
            $table->foreignId('document_type_id')->constrained('document_types')->cascadeOnDelete();
            $table->foreignId('private_file_id')->constrained('private_files')->cascadeOnDelete();
            $table->unsignedInteger('reviewed_version_number');
            $table->string('review_status', 32)->default('PENDING'); // PENDING, ACCEPTED, NEEDS_CORRECTION
            $table->string('rejection_reason', 255)->nullable();
            $table->text('customer_notes')->nullable();
            $table->timestampsTz();

            $table->index(['billing_request_id', 'document_type_id'], 'br_docs_request_type_idx');
        });

        // 4. Billing Request Events (Audit trail of queue movements and decisions)
        Schema::create('billing_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_request_id')->constrained('billing_requests')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 64); // SUBMITTED, ADMITTED, CLAIMED, HEARTBEAT, CORRECTION_REQUESTED, RESUBMITTED, DRAFT_PREPARED, BILL_READY, ASSIGNMENT_RECOVERED, RELEASED, CANCELLED
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->default('[]');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['billing_request_id', 'created_at'], 'br_events_request_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_request_events');
        Schema::dropIfExists('billing_request_documents');
        Schema::dropIfExists('billing_requests');
        Schema::dropIfExists('queue_tickets');
    }
};
