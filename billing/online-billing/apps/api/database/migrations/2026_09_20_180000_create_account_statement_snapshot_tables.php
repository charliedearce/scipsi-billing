<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->foreignId('location_id')->nullable()->constrained('locations')->onDelete('restrict');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->string('statement_number', 64);
            $table->date('as_of_date');
            $table->string('currency', 3)->default('PHP');
            $table->decimal('invoice_total', 14, 2)->default(0);
            $table->decimal('payment_total', 14, 2)->default(0);
            $table->decimal('outstanding_total', 14, 2)->default(0);
            $table->jsonb('customer_snapshot');
            $table->string('status', 16)->default('GENERATED'); // GENERATED, VOID
            $table->foreignId('generated_by_user_id')->constrained('users')->onDelete('restrict');
            $table->timestampTz('generated_at');
            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'statement_number']);
            $table->index(['organization_id', 'customer_id', 'as_of_date']);
        });

        Schema::create('account_statement_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('statement_id')->constrained('account_statements')->onDelete('restrict');
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('restrict');
            $table->string('invoice_number', 64);
            $table->date('business_date');
            $table->decimal('invoice_amount', 14, 2);
            $table->decimal('payment_amount', 14, 2);
            $table->decimal('outstanding_amount', 14, 2);
            $table->jsonb('snapshot');
            $table->timestampsTz();

            $table->unique(['statement_id', 'invoice_id']);
            $table->index(['invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_statement_items');
        Schema::dropIfExists('account_statements');
    }
};
