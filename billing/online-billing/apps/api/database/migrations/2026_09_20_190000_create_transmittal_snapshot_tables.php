<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transmittals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->foreignId('location_id')->nullable()->constrained('locations')->onDelete('restrict');
            $table->string('transmittal_number', 64);
            $table->string('kind', 32); // YELLOW_INVOICE, WHITE_RECEIPT
            $table->date('as_of_date');
            $table->string('currency', 3);
            $table->unsignedInteger('source_item_count');
            $table->jsonb('summary');
            $table->string('status', 16)->default('GENERATED'); // GENERATED, VOID
            $table->foreignId('generated_by_user_id')->constrained('users')->onDelete('restrict');
            $table->timestampTz('generated_at');
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'transmittal_number']);
            $table->index(['organization_id', 'kind', 'as_of_date']);
            $table->index(['organization_id', 'location_id', 'generated_at']);
        });

        // Separate tables deliberately keep referential integrity for the two source types.
        // A polymorphic source_id would make a future source document deletion unsafe.
        Schema::create('yellow_transmittal_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transmittal_id')->constrained('transmittals')->onDelete('restrict');
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('restrict');
            $table->string('invoice_number', 64);
            $table->date('business_date');
            $table->string('currency', 3);
            $table->decimal('total_charge_amount', 14, 2);
            $table->jsonb('buyer_snapshot');
            $table->jsonb('source_snapshot');
            $table->timestampsTz();

            $table->unique(['transmittal_id', 'invoice_id']);
            $table->index(['invoice_id']);
        });

        Schema::create('white_transmittal_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transmittal_id')->constrained('transmittals')->onDelete('restrict');
            $table->foreignId('receipt_id')->constrained('receipts')->onDelete('restrict');
            $table->string('receipt_number', 64);
            $table->date('business_date');
            $table->string('currency', 3);
            $table->decimal('cash_received_amount', 14, 2);
            $table->decimal('withholding_received_amount', 14, 2);
            $table->decimal('applied_amount', 14, 2);
            $table->decimal('unapplied_amount', 14, 2);
            $table->jsonb('payer_snapshot');
            $table->jsonb('source_snapshot');
            $table->timestampsTz();

            $table->unique(['transmittal_id', 'receipt_id']);
            $table->index(['receipt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('white_transmittal_items');
        Schema::dropIfExists('yellow_transmittal_items');
        Schema::dropIfExists('transmittals');
    }
};
