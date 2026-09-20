<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_payment_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('proof_file_id')->constrained('private_files')->onDelete('restrict');
            $table->foreignId('receipt_id')->nullable()->unique()->constrained('receipts')->onDelete('restrict');
            $table->foreignId('submitted_by_user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->string('source_key', 128);
            $table->string('status', 32)->default('SUBMITTED'); // SUBMITTED, IN_REVIEW, REJECTED, APPROVED
            $table->string('currency', 3)->default('PHP');
            $table->decimal('requested_amount', 14, 2);
            $table->string('declared_reference', 128)->nullable();
            $table->string('confirmed_reference', 128)->nullable();
            $table->timestampTz('initial_submitted_at');
            $table->timestampTz('submitted_at');
            $table->timestampTz('assigned_at')->nullable();
            $table->timestampTz('assignment_heartbeat_at')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('resubmission_rounds')->default(0);
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('approval_payload_fingerprint', 64)->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'source_key'], 'manual_payment_submission_source_unique');
            $table->index(['organization_id', 'status', 'initial_submitted_at'], 'manual_payment_submission_queue_index');
            $table->index(['organization_id', 'customer_id', 'status'], 'manual_payment_submission_customer_index');
        });

        Schema::create('manual_payment_submission_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manual_payment_submission_id')->constrained('manual_payment_submissions')->onDelete('restrict');
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('restrict');
            $table->unsignedInteger('expected_invoice_lock_version');
            $table->decimal('requested_amount', 14, 2);
            $table->timestampsTz();

            $table->unique(['manual_payment_submission_id', 'invoice_id'], 'manual_payment_submission_item_unique');
            $table->index(['invoice_id', 'created_at']);
        });

        // Captures the exact private-file version that was reviewed on each attempt.
        Schema::create('manual_payment_submission_proofs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manual_payment_submission_id')->constrained('manual_payment_submissions')->onDelete('restrict');
            $table->foreignId('private_file_id')->constrained('private_files')->onDelete('restrict');
            $table->unsignedInteger('private_file_version_number');
            $table->unsignedInteger('attempt_number');
            $table->foreignId('submitted_by_user_id')->constrained('users')->onDelete('restrict');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['manual_payment_submission_id', 'attempt_number'], 'manual_payment_submission_proof_attempt_unique');
            $table->unique(['private_file_id', 'private_file_version_number'], 'manual_payment_proof_file_version_unique');
        });

        Schema::create('manual_payment_submission_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manual_payment_submission_id')->constrained('manual_payment_submissions')->onDelete('restrict');
            $table->foreignId('actor_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->string('event_type', 48); // SUBMITTED, CLAIMED, REJECTED, RESUBMITTED, APPROVED
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['manual_payment_submission_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_payment_submission_events');
        Schema::dropIfExists('manual_payment_submission_proofs');
        Schema::dropIfExists('manual_payment_submission_items');
        Schema::dropIfExists('manual_payment_submissions');
    }
};
