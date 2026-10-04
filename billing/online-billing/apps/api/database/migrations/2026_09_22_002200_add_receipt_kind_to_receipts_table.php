<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distinguishes fiscal Official Receipts from internal Acknowledgement Receipts.
 *
 * Acknowledgement receipts still post settlement/allocations/history. They do not
 * count as official-receipt / BIR-facing OR issuances. Existing rows default to OFFICIAL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->string('receipt_kind', 32)->default('OFFICIAL')->after('status');
            $table->boolean('counts_as_official_receipt')->default(true)->after('receipt_kind');
            $table->index(['organization_id', 'receipt_kind', 'business_date'], 'receipts_org_kind_date_idx');
            $table->index(['organization_id', 'counts_as_official_receipt', 'business_date'], 'receipts_org_fiscal_or_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->dropIndex('receipts_org_kind_date_idx');
            $table->dropIndex('receipts_org_fiscal_or_date_idx');
            $table->dropColumn(['receipt_kind', 'counts_as_official_receipt']);
        });
    }
};
