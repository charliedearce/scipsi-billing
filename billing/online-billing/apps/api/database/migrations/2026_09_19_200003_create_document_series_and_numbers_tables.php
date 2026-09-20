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
        // 1. Document Series (Configured registers/series for sales invoices, official receipts, etc.)
        Schema::create('document_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('document_type', 64); // SALES_INVOICE, COLLECTION_RECEIPT
            $table->string('series_code', 64); // e.g. SI-GENSAN-2026
            $table->string('prefix', 32)->default('SI-');
            $table->unsignedBigInteger('current_number')->default(0);
            $table->unsignedBigInteger('start_number')->default(1);
            $table->unsignedBigInteger('end_number')->nullable(); // null for open-ended, number for capped preprinted series
            $table->integer('padding_length')->default(10);
            $table->boolean('is_active')->default(true);
            $table->integer('lock_version')->default(1);
            $table->timestampsTz();

            $table->unique(['organization_id', 'series_code']);
            $table->index(['organization_id', 'document_type', 'is_active']);
        });

        // 2. Document Numbers (Monotonic allocated and issued numbers register)
        Schema::create('document_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('series_id')->constrained('document_series')->onDelete('restrict');
            $table->string('document_type', 64);
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedBigInteger('sequence_number');
            $table->string('formatted_number', 64);
            $table->string('status', 32)->default('ALLOCATED'); // ALLOCATED, ISSUED, VOID
            $table->timestampTz('allocated_at');
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('allocated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['series_id', 'sequence_number']);
            $table->unique(['organization_id', 'formatted_number']);
            $table->index(['document_type', 'document_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_numbers');
        Schema::dropIfExists('document_series');
    }
};
