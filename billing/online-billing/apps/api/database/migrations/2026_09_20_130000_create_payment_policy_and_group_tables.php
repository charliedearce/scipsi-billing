<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->unsignedInteger('version_number');
            $table->string('currency', 3)->default('PHP');
            $table->boolean('gateway_enabled')->default(false);
            $table->decimal('gateway_threshold_amount', 14, 2)->nullable();
            $table->text('manual_instructions');
            $table->unsignedInteger('manual_deadline_hours');
            $table->unsignedInteger('review_target_hours')->nullable();
            $table->unsignedInteger('clearance_target_hours')->nullable();
            $table->unsignedInteger('correction_window_hours')->nullable();
            $table->string('status', 16)->default('DRAFT');
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestampTz('published_at')->nullable();
            $table->text('publication_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();

            $table->unique(['organization_id', 'version_number'], 'payment_policy_version_unique');
            $table->index(['organization_id', 'status', 'effective_from'], 'payment_policy_effective_index');
        });

        Schema::create('payment_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('payment_policy_version_id')->constrained('payment_policy_versions')->onDelete('restrict');
            $table->unsignedInteger('payment_policy_version_number');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('restrict');
            $table->string('source_key', 128);
            $table->string('route', 32); // MANUAL_BANK now; P3-06 adds verified gateway routes.
            $table->string('status', 32)->default('MANUAL_INSTRUCTION_ISSUED');
            $table->string('currency', 3);
            $table->decimal('gross_selected_amount', 14, 2);
            $table->decimal('gateway_threshold_snapshot', 14, 2)->nullable();
            $table->text('manual_instructions_snapshot');
            $table->unsignedInteger('manual_deadline_hours_snapshot');
            $table->timestampTz('instruction_issued_at');
            $table->timestampTz('payment_deadline_at');
            $table->timestampTz('first_proof_submitted_at')->nullable();
            $table->timestampTz('last_proof_submitted_at')->nullable();
            $table->boolean('first_proof_was_timely')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();

            $table->unique(['organization_id', 'source_key'], 'payment_group_source_unique');
            $table->index(['organization_id', 'customer_id', 'status'], 'payment_group_customer_status_index');
            $table->index(['organization_id', 'payment_deadline_at'], 'payment_group_deadline_index');
        });

        Schema::create('payment_group_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_group_id')->constrained('payment_groups')->onDelete('restrict');
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('restrict');
            $table->unsignedInteger('expected_invoice_lock_version');
            $table->decimal('requested_amount', 14, 2);
            $table->timestampsTz();

            $table->unique(['payment_group_id', 'invoice_id'], 'payment_group_item_unique');
            $table->index(['invoice_id', 'created_at'], 'payment_group_item_invoice_index');
        });

        Schema::create('payment_group_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_group_id')->constrained('payment_groups')->onDelete('restrict');
            $table->foreignId('actor_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->string('event_type', 48);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['payment_group_id', 'created_at'], 'payment_group_event_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_group_events');
        Schema::dropIfExists('payment_group_items');
        Schema::dropIfExists('payment_groups');
        Schema::dropIfExists('payment_policy_versions');
    }
};
