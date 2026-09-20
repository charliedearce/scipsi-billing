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
        // 1. Document Revisions (Append-only snapshots of drafts and editable records)
        Schema::create('document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->foreignId('location_id')->nullable()->constrained('locations')->onDelete('restrict');
            $table->string('document_type', 120);
            $table->unsignedBigInteger('document_id');
            $table->integer('revision_number');
            $table->string('actor_type', 120)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('reason')->nullable();
            $table->jsonb('changed_fields')->default('[]');
            $table->jsonb('snapshot');
            $table->string('snapshot_hash', 64);
            $table->integer('lock_version')->default(1);
            $table->timestampTz('created_at')->useCurrent();

            // Foreign key to users if actor is a user
            $table->foreign('actor_id')->references('id')->on('users')->onDelete('restrict');

            // Unique constraint: strictly one revision number per document in an organization
            $table->unique(['organization_id', 'document_type', 'document_id', 'revision_number'], 'doc_rev_org_type_id_num_unique');
            $table->index(['organization_id', 'document_type', 'document_id', 'created_at'], 'doc_rev_lookup_idx');
            $table->index(['actor_id', 'created_at'], 'doc_rev_actor_idx');
        });

        // 2. Business Audit Events (Append-only commands, postings, approvals, and reviews)
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->foreignId('location_id')->nullable()->constrained('locations')->onDelete('restrict');
            $table->string('event_type', 80);
            $table->string('aggregate_type', 120);
            $table->unsignedBigInteger('aggregate_id');
            $table->integer('aggregate_version')->default(1);
            $table->string('actor_type', 120)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('permission_snapshot', 100)->nullable();
            $table->timestampTz('occurred_at')->useCurrent();
            $table->date('business_date')->nullable();
            $table->text('reason')->nullable();
            $table->string('request_id', 64)->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->string('idempotency_key', 64)->nullable();
            $table->foreignId('parent_event_id')->nullable()->constrained('audit_events')->onDelete('restrict');
            $table->jsonb('before_snapshot')->nullable();
            $table->jsonb('after_snapshot')->nullable();
            $table->jsonb('metadata')->nullable();

            // Foreign key to users if actor is a user
            $table->foreign('actor_id')->references('id')->on('users')->onDelete('restrict');

            $table->index(['organization_id', 'aggregate_type', 'aggregate_id', 'occurred_at'], 'audit_events_aggregate_idx');
            $table->index(['correlation_id'], 'audit_events_correlation_idx');
            $table->index(['actor_id', 'occurred_at'], 'audit_events_actor_idx');
            $table->index(['event_type', 'occurred_at'], 'audit_events_type_idx');
        });

        // 3. Document Correction Links (Explicit links connecting corrected documents to adjustments)
        Schema::create('document_correction_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->string('original_document_type', 120);
            $table->unsignedBigInteger('original_document_id');
            $table->string('correction_document_type', 120);
            $table->unsignedBigInteger('correction_document_id');
            $table->string('correction_type', 40); // CORRECTION, REVERSAL, ADJUSTMENT, REPLACEMENT
            $table->text('reason');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['organization_id', 'original_document_type', 'original_document_id'], 'corr_links_orig_idx');
            $table->index(['organization_id', 'correction_document_type', 'correction_document_id'], 'corr_links_corr_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_correction_links');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('document_revisions');
    }
};
