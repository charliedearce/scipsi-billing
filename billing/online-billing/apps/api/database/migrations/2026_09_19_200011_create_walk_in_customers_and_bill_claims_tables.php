<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Walk-in customer records created by tellers without a portal login.
        // Separate from portal-registered Customer records until explicitly linked.
        Schema::create('walk_in_customers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('location_id');

            // Buyer fields required for the invoice — supplied by teller at counter
            $table->string('buyer_name', 255);
            $table->string('buyer_tin', 32)->nullable();
            $table->string('buyer_branch_code', 10)->nullable();
            $table->text('buyer_address')->nullable();

            // Optional contact channels for claim-code delivery
            $table->string('contact_mobile', 32)->nullable();
            $table->string('contact_email', 255)->nullable();

            // Portal customer link — set when a portal user successfully claims a walk-in invoice
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->timestampTz('linked_at')->nullable();
            $table->unsignedBigInteger('linked_by_user_id')->nullable();

            $table->unsignedBigInteger('created_by_user_id');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();

            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('location_id')->references('id')->on('locations');
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('linked_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users');

            $table->index(['organization_id', 'customer_id']);
            $table->index(['organization_id', 'created_by_user_id']);
        });

        // Portal-user invoice-number claim requests.
        // Security design: number-only lookup never leaks invoice existence.
        // Verification routes: CLAIM_CODE (contact-matched OTP) or TELLER_REVIEW (staff identity check).
        Schema::create('bill_claim_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('organization_id');

            // The requesting portal user and their associated customer account
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('customer_id');

            // Invoice number entered by the user (exact string, not an FK initially)
            $table->string('invoice_number', 64);

            // Resolved once the number is matched and the claim is approved
            $table->unsignedBigInteger('invoice_id')->nullable();

            $table->string('claim_status', 32)->default('PENDING_VERIFICATION');
            // Statuses: PENDING_VERIFICATION, PENDING_TELLER_REVIEW, APPROVED, REJECTED, EXPIRED, CANCELLED

            $table->string('verification_route', 32);
            // Routes: CLAIM_CODE, TELLER_REVIEW

            // Claim-code credentials — raw code is NEVER stored; only SHA-256(code + salt)
            $table->string('code_hash', 64)->nullable();
            $table->string('code_salt', 64)->nullable();
            $table->timestampTz('code_expires_at')->nullable();

            // Rate limiting
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);

            // Resolution
            $table->timestampTz('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('staff_notes')->nullable();

            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();

            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->foreign('resolved_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['organization_id', 'user_id', 'claim_status']);
            $table->index(['organization_id', 'invoice_number']);
            $table->index(['organization_id', 'claim_status', 'verification_route']);
        });

        // Immutable audit trail for every bill claim state transition.
        Schema::create('bill_claim_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bill_claim_request_id');
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('actor_id')->nullable();

            $table->string('event_type', 64);
            // Types: CLAIM_INITIATED, CODE_ISSUED, CODE_VERIFIED, TELLER_REVIEW_QUEUED,
            //        APPROVED, REJECTED, EXPIRED, CANCELLED, CODE_ATTEMPT_FAILED

            $table->text('notes')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestampTz('created_at');

            $table->foreign('bill_claim_request_id')->references('id')->on('bill_claim_requests')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['bill_claim_request_id', 'created_at']);
            $table->index(['organization_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_claim_events');
        Schema::dropIfExists('bill_claim_requests');
        Schema::dropIfExists('walk_in_customers');
    }
};
