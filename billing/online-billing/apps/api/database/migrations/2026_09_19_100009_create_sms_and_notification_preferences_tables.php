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
        // 1. Customer Contact Points
        Schema::create('customer_contact_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 32)->default('mobile'); // mobile, email
            $table->string('value', 64);
            $table->boolean('is_verified')->default(false);
            $table->timestampTz('verified_at')->nullable();
            $table->string('status', 32)->default('active'); // active, opted_out, stale, bounced
            $table->integer('version')->default(1);
            $table->integer('lock_version')->default(1);
            $table->timestampsTz();

            $table->unique(['user_id', 'type', 'value']);
            $table->index(['organization_id', 'status']);
            $table->index(['type', 'value']);
        });

        // 2. Contact Verification Events
        Schema::create('contact_verification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_point_id')->constrained('customer_contact_points')->cascadeOnDelete();
            $table->string('verification_method', 64); // otp_sms, staff_verified, email_link
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampTz('verified_at');
            $table->timestampsTz();

            $table->index(['contact_point_id', 'verified_at']);
        });

        // 3. Notification Preference Versions
        Schema::create('notification_preference_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('version')->default(1);
            $table->jsonb('preferences'); // map of event_type => [sms => bool, in_app => bool, etc.]
            $table->timestampTz('effective_from');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['user_id', 'version']);
            $table->index(['organization_id', 'user_id']);
        });

        // 4. Notification Templates
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 64); // e.g. BILLING_REQUEST_QUEUED
            $table->string('name', 128);
            $table->string('channel', 32)->default('sms');
            $table->string('template_class', 32); // CONTRACTUAL_TRANSACTIONAL, OPERATIONAL_REMINDER
            $table->integer('current_version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['organization_id', 'code', 'channel']);
        });

        // 5. Notification Template Versions
        Schema::create('notification_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('notification_templates')->cascadeOnDelete();
            $table->integer('version')->default(1);
            $table->text('body_template');
            $table->jsonb('allowed_variables')->nullable();
            $table->string('status', 32)->default('draft'); // draft, published, active, retired
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('activated_at')->nullable();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('retired_at')->nullable();
            $table->foreignId('retired_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['template_id', 'version']);
            $table->index(['template_id', 'status']);
        });

        // 6. Notification Policy Versions
        Schema::create('notification_policy_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('event_key', 64);
            $table->integer('version')->default(1);
            $table->foreignId('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->foreignId('template_version_id')->nullable()->constrained('notification_template_versions')->nullOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->string('priority', 16)->default('normal'); // normal, high
            $table->string('allowed_channel', 32)->default('sms');
            $table->jsonb('quiet_hours_policy')->nullable();
            $table->timestampTz('effective_from');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['organization_id', 'event_key', 'version']);
        });

        // 7. Notification Events (Post-Commit Immutable Event Log)
        Schema::create('notification_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('event_key', 64);
            $table->string('event_source_type', 64);
            $table->unsignedBigInteger('event_source_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->jsonb('payload_snapshot');
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['organization_id', 'event_key']);
            $table->index(['event_source_type', 'event_source_id']);
            $table->index(['user_id', 'event_key']);
        });

        // 8. Notification Deliveries (Outbox Record)
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('notification_events')->cascadeOnDelete();
            $table->foreignId('contact_point_id')->nullable()->constrained('customer_contact_points')->nullOnDelete();
            $table->string('recipient_phone', 32);
            $table->foreignId('template_version_id')->constrained('notification_template_versions')->cascadeOnDelete();
            $table->foreignId('policy_version_id')->nullable()->constrained('notification_policy_versions')->nullOnDelete();
            $table->string('channel', 32)->default('sms');
            $table->text('rendered_body');
            $table->string('rendered_body_hash', 64);
            $table->string('local_effect_key', 128)->unique();
            $table->string('status', 32)->default('queued_local');
            // suppressed, queued_local, dispatching, provider_pending, provider_queued, provider_sent, provider_failed, unknown_reconciliation_required
            $table->string('suppression_reason', 255)->nullable();
            $table->integer('attempt_count')->default(0);
            $table->timestampTz('last_attempted_at')->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->timestampsTz();

            $table->index(['organization_id', 'status']);
            $table->index(['status']);
            $table->index(['event_id']);
        });

        // 9. SMS Delivery Attempts
        Schema::create('sms_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('notification_deliveries')->cascadeOnDelete();
            $table->integer('attempt_number')->default(1);
            $table->string('provider', 32)->default('fake');
            $table->string('provider_queue_id', 128)->nullable();
            $table->string('provider_message_id', 128)->nullable();
            $table->string('status', 32); // success, failed, timeout, unknown
            $table->text('error_message')->nullable();
            $table->jsonb('response_payload_redacted')->nullable();
            $table->timestampTz('dispatched_at');
            $table->timestampTz('response_received_at')->nullable();
            $table->timestampsTz();

            $table->index(['delivery_id', 'attempt_number']);
            $table->index(['provider', 'provider_queue_id']);
        });

        // 10. SMS Provider Status Observations
        Schema::create('sms_provider_status_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('notification_deliveries')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_raw_status', 64);
            $table->string('normalized_status', 64);
            $table->string('observation_source', 32); // send_response, poll_reconciliation, status_endpoint
            $table->timestampTz('observed_at');
            $table->jsonb('details_redacted')->nullable();
            $table->timestampsTz();

            $table->index(['delivery_id', 'observed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_provider_status_observations');
        Schema::dropIfExists('sms_delivery_attempts');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_events');
        Schema::dropIfExists('notification_policy_versions');
        Schema::dropIfExists('notification_template_versions');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notification_preference_versions');
        Schema::dropIfExists('contact_verification_events');
        Schema::dropIfExists('customer_contact_points');
    }
};
