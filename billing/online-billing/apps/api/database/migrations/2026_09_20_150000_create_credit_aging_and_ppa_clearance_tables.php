<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_credit_charges', function (Blueprint $table): void {
            // A credit charge has both a business date (for as-of aging) and a recorded timestamp.
            // Future charges always set this explicitly; a nullable field preserves imported exceptions.
            $table->date('charged_business_date')->nullable()->after('charged_at');
            $table->date('due_date')->nullable()->change();
            $table->index(['customer_credit_account_id', 'charged_business_date'], 'credit_charge_account_business_date_index');
        });

        DB::statement("UPDATE invoice_credit_charges
            SET charged_business_date = (charged_at AT TIME ZONE 'Asia/Manila')::date
            WHERE charged_business_date IS NULL AND charged_at IS NOT NULL");

        Schema::create('ppa_clearance_policy_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->unsignedInteger('version_number');
            // A missing or inactive policy is deliberately equivalent to full payment required.
            $table->boolean('accept_qualifying_vip_credit')->default(false);
            $table->string('status', 16)->default('DRAFT');
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestampTz('published_at')->nullable();
            $table->text('publication_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestampsTz();

            $table->unique(['organization_id', 'version_number'], 'ppa_clearance_policy_version_unique');
            $table->index(['organization_id', 'status', 'effective_from'], 'ppa_clearance_policy_effective_index');
        });

        // This is an audit of a read-only PPA validation, never a release, payment or clearance action.
        Schema::create('ppa_verification_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('restrict');
            $table->foreignId('invoice_credit_charge_id')->nullable()->constrained('invoice_credit_charges')->onDelete('restrict');
            $table->foreignId('ppa_clearance_policy_version_id')->nullable()->constrained('ppa_clearance_policy_versions')->onDelete('restrict');
            $table->unsignedInteger('ppa_clearance_policy_version_number')->nullable();
            $table->foreignId('checked_by_user_id')->constrained('users')->onDelete('restrict');
            $table->string('settlement_status', 32);
            $table->string('credit_status', 32);
            $table->string('clearance_eligibility', 48);
            $table->decimal('outstanding_amount', 14, 2);
            $table->timestampTz('checked_at');
            $table->timestampsTz();

            $table->index(['organization_id', 'invoice_id', 'checked_at'], 'ppa_verification_invoice_timeline_index');
            $table->index(['checked_by_user_id', 'checked_at'], 'ppa_verification_actor_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppa_verification_events');
        Schema::dropIfExists('ppa_clearance_policy_versions');

        Schema::table('invoice_credit_charges', function (Blueprint $table): void {
            $table->dropIndex('credit_charge_account_business_date_index');
            $table->dropColumn('charged_business_date');
            $table->date('due_date')->nullable(false)->change();
        });
    }
};
