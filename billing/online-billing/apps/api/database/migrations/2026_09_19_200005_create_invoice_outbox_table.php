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
        Schema::create('invoice_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('event_type', 64); // INVOICE_POSTED
            $table->jsonb('payload');
            $table->string('status', 32)->default('PENDING'); // PENDING, DISPATCHED, FAILED
            $table->timestampTz('dispatched_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestampsTz();

            $table->index(['organization_id', 'status']);
            $table->index(['invoice_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_outbox');
    }
};
