<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_correction_requests', function (Blueprint $table): void {
            $table->foreignId('correction_draft_invoice_id')
                ->nullable()
                ->after('invoice_id')
                ->constrained('invoices')
                ->nullOnDelete();
            $table->foreignId('replacement_invoice_id')
                ->nullable()
                ->after('correction_draft_invoice_id')
                ->constrained('invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_correction_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('replacement_invoice_id');
            $table->dropConstrainedForeignId('correction_draft_invoice_id');
        });
    }
};
