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
        // 1. Customers (Organization-scoped business account)
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('account_number', 64)->unique();
            $table->string('name', 255); // Company / registered buyer name
            $table->string('status', 32)->default('pending'); // pending, active, suspended, archived
            $table->string('customer_type', 32)->default('business'); // business, individual
            $table->integer('lock_version')->default(1);
            $table->timestampsTz();

            $table->index(['organization_id', 'status']);
            $table->index(['name']);
        });

        // 2. Customer User Links (Explicit authorization row binding user to customer account)
        Schema::create('customer_user_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('authority_role', 32)->default('member'); // owner, admin, billing_contact, member
            $table->boolean('is_active')->default(true);
            $table->timestampTz('linked_at');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['customer_id', 'user_id']);
            $table->index(['user_id', 'is_active']);
        });

        // 3. Customer Buyer Profiles (Root aggregate for buyer identity)
        Schema::create('customer_buyer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->integer('current_version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['customer_id']);
        });

        // 4. Buyer Profile Versions (Immutable versioned billing details)
        Schema::create('buyer_profile_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_profile_id')->constrained('customer_buyer_profiles')->cascadeOnDelete();
            $table->integer('version')->default(1);
            $table->string('registered_name', 255); // Official company / registered buyer name
            $table->string('trade_name', 255)->nullable();
            $table->string('tin', 32)->nullable();
            $table->string('branch_code', 16)->default('00000');
            $table->string('tax_classification', 32)->default('REGULAR'); // REGULAR, ZERO_RATED, EXEMPT
            $table->jsonb('billing_address')->nullable();
            $table->string('contact_email', 128)->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->timestampTz('effective_from');
            $table->string('status', 32)->default('active'); // active, draft, superseded
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['buyer_profile_id', 'version']);
            $table->index(['buyer_profile_id', 'status']);
        });

        // 5. Contact Verification Challenges (Purpose-bound OTP challenges)
        Schema::create('contact_verification_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_point_id')->constrained('customer_contact_points')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purpose', 32); // REGISTRATION_MOBILE, REGISTRATION_EMAIL, MOBILE_CHANGE, WALK_IN_CLAIM
            $table->string('challenge_code_hash', 64); // Salted SHA-256 hash, NEVER plaintext
            $table->string('challenge_salt', 32);
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->string('provider', 32)->default('fake');
            $table->string('provider_reference_redacted', 128)->nullable();
            $table->timestampsTz();

            $table->index(['contact_point_id', 'purpose']);
            $table->index(['expires_at', 'consumed_at']);
        });

        // 6. Link Customer to Customer Contact Points
        Schema::table('customer_contact_points', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('user_id')->constrained('customers')->nullOnDelete();
            $table->index(['customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_contact_points', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::dropIfExists('contact_verification_challenges');
        Schema::dropIfExists('buyer_profile_versions');
        Schema::dropIfExists('customer_buyer_profiles');
        Schema::dropIfExists('customer_user_links');
        Schema::dropIfExists('customers');
    }
};
