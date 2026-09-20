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
        // 1. Taxpayer Profile Versions (Issuer Fiscal Identity)
        Schema::create('taxpayer_profile_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->integer('version')->default(1);
            $table->string('registered_name', 255);
            $table->string('trade_name', 255)->nullable();
            $table->string('tin', 32);
            $table->string('branch_code', 16)->default('00000');
            $table->string('tax_classification', 32)->default('VAT_REGISTERED'); // VAT_REGISTERED, NON_VAT
            $table->string('rdo_code', 16)->nullable();
            $table->jsonb('registered_address');
            $table->string('line_of_business', 255)->nullable();
            $table->string('bir_permit_number', 64)->nullable();
            $table->date('bir_permit_issued_at')->nullable();
            $table->string('statutory_legend', 255)->nullable();
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'version'], 'taxpayer_profiles_org_ver_unique');
            $table->index(['organization_id', 'is_active', 'effective_from'], 'taxpayer_profiles_org_active_idx');
        });

        // 2. Fiscal Tax Rule Versions (Tax rates and statutory requirements)
        Schema::create('fiscal_tax_rule_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->integer('version')->default(1);
            $table->string('tax_classification_key', 32); // VATABLE, ZERO_RATED, EXEMPT, NON_VAT
            $table->decimal('vat_rate', 6, 4)->default(0.1200);
            $table->boolean('buyer_tin_required')->default(true);
            $table->string('invoice_legend', 255)->nullable();
            $table->string('legal_basis', 255)->nullable();
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'tax_classification_key', 'version'], 'fiscal_rules_org_key_ver_unique');
            $table->index(['organization_id', 'tax_classification_key', 'is_active'], 'fiscal_rules_org_key_active_idx');
        });

        // 3. Add issuer snapshot & sale type to invoices table
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('taxpayer_profile_version_id')->nullable()->after('buyer_profile_version_id')->constrained('taxpayer_profile_versions')->nullOnDelete();
            $table->string('issuer_snapshot_name', 255)->nullable()->after('buyer_snapshot_phone');
            $table->string('issuer_snapshot_trade_name', 255)->nullable()->after('issuer_snapshot_name');
            $table->string('issuer_snapshot_tin', 32)->nullable()->after('issuer_snapshot_trade_name');
            $table->string('issuer_snapshot_branch_code', 16)->nullable()->after('issuer_snapshot_tin');
            $table->string('issuer_snapshot_tax_classification', 32)->nullable()->after('issuer_snapshot_branch_code');
            $table->jsonb('issuer_snapshot_address')->nullable()->after('issuer_snapshot_tax_classification');
            $table->string('issuer_snapshot_permit_no', 64)->nullable()->after('issuer_snapshot_address');
            $table->string('sale_type', 20)->default('CASH')->after('notes'); // CASH, CREDIT
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['taxpayer_profile_version_id']);
            $table->dropColumn([
                'taxpayer_profile_version_id',
                'issuer_snapshot_name',
                'issuer_snapshot_trade_name',
                'issuer_snapshot_tin',
                'issuer_snapshot_branch_code',
                'issuer_snapshot_tax_classification',
                'issuer_snapshot_address',
                'issuer_snapshot_permit_no',
                'sale_type',
            ]);
        });

        Schema::dropIfExists('fiscal_tax_rule_versions');
        Schema::dropIfExists('taxpayer_profile_versions');
    }
};
