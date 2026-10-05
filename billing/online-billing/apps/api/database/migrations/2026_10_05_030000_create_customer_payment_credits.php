<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payment_credits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict');
            $table->foreignId('source_receipt_id')->unique()->constrained('receipts')->onDelete('restrict');
            $table->string('currency', 3);
            $table->decimal('original_amount', 14, 2);
            $table->timestampsTz();

            $table->index(['organization_id', 'customer_id', 'currency', 'id'], 'payment_credit_customer_index');
        });

        Schema::create('customer_payment_credit_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('credit_id')->constrained('customer_payment_credits')->onDelete('restrict');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->onDelete('restrict');
            $table->string('type', 16);
            $table->decimal('amount', 14, 2);
            $table->string('source_key', 160);
            $table->string('checkout_source_key', 128)->nullable();
            $table->foreignId('actor_user_id')->constrained('users')->onDelete('restrict');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['credit_id', 'source_key'], 'payment_credit_movement_source_unique');
            $table->index(['invoice_id', 'type'], 'payment_credit_invoice_type_index');
            $table->index(['checkout_source_key', 'type'], 'payment_credit_checkout_index');
        });

        Schema::table('payment_groups', function (Blueprint $table): void {
            $table->decimal('credit_applied_amount', 14, 2)->default(0);
            $table->decimal('cash_due_amount', 14, 2)->default(0);
            $table->timestampTz('instruction_issued_at')->nullable()->change();
            $table->timestampTz('payment_deadline_at')->nullable()->change();
        });
        DB::table('payment_groups')->update(['cash_due_amount' => DB::raw('gross_selected_amount')]);
    }

    public function down(): void
    {
        if (DB::table('customer_payment_credits')->exists()
            || DB::table('payment_groups')->where('route', 'CUSTOMER_CREDIT')->exists()) {
            throw new RuntimeException('Reconcile customer payment credits and credit-only payment groups before rolling back this migration.');
        }
        Schema::table('payment_groups', function (Blueprint $table): void {
            $table->dropColumn(['credit_applied_amount', 'cash_due_amount']);
            $table->timestampTz('instruction_issued_at')->nullable(false)->change();
            $table->timestampTz('payment_deadline_at')->nullable(false)->change();
        });
        Schema::dropIfExists('customer_payment_credit_movements');
        Schema::dropIfExists('customer_payment_credits');
    }
};
