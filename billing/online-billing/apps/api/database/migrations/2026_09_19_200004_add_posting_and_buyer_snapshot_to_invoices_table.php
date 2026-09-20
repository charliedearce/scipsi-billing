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
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->after('invoice_number')->constrained('document_series')->nullOnDelete();
            $table->timestampTz('posted_at')->nullable()->after('currency');
            $table->foreignId('posted_by_user_id')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();

            // Immutable BIR Buyer Snapshot captured at issuance time (Decision W32 / BIR-06)
            $table->string('buyer_snapshot_name', 255)->nullable()->after('posted_by_user_id');
            $table->string('buyer_snapshot_trade_name', 255)->nullable()->after('buyer_snapshot_name');
            $table->string('buyer_snapshot_tin', 32)->nullable()->after('buyer_snapshot_trade_name');
            $table->string('buyer_snapshot_branch_code', 16)->nullable()->after('buyer_snapshot_tin');
            $table->string('buyer_snapshot_tax_classification', 32)->nullable()->after('buyer_snapshot_branch_code');
            $table->jsonb('buyer_snapshot_address')->nullable()->after('buyer_snapshot_tax_classification');
            $table->string('buyer_snapshot_email', 128)->nullable()->after('buyer_snapshot_address');
            $table->string('buyer_snapshot_phone', 32)->nullable()->after('buyer_snapshot_email');

            $table->index(['organization_id', 'status', 'posted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'status', 'posted_at']);
            $table->dropConstrainedForeignId('series_id');
            $table->dropConstrainedForeignId('posted_by_user_id');
            $table->dropColumn([
                'posted_at',
                'buyer_snapshot_name',
                'buyer_snapshot_trade_name',
                'buyer_snapshot_tin',
                'buyer_snapshot_branch_code',
                'buyer_snapshot_tax_classification',
                'buyer_snapshot_address',
                'buyer_snapshot_email',
                'buyer_snapshot_phone',
            ]);
        });
    }
};
