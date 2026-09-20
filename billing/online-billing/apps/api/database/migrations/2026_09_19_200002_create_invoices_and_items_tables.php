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
        // 1. Invoices (Draft, review, and issued invoice records)
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('buyer_profile_version_id')->nullable()->constrained('buyer_profile_versions')->nullOnDelete();
            $table->string('invoice_number', 64)->nullable();
            $table->string('status', 32)->default('DRAFT'); // DRAFT, PENDING_REVIEW, POSTED, CANCELLED
            $table->date('business_date');
            $table->string('currency', 3)->default('PHP');

            // Decimal Totals (14, 2 for PHP currency values)
            $table->decimal('base_gross_amount', 14, 2)->default(0.00);
            $table->decimal('fuel_surcharge_amount', 14, 2)->default(0.00);
            $table->decimal('gross_amount', 14, 2)->default(0.00);
            $table->decimal('ppa_amount', 14, 2)->default(0.00);
            $table->decimal('discount_amount', 14, 2)->default(0.00);
            $table->decimal('net_amount', 14, 2)->default(0.00);
            $table->decimal('tax_amount', 14, 2)->default(0.00);
            $table->decimal('total_charge_amount', 14, 2)->default(0.00);

            // Validation & Concurrency
            $table->boolean('is_fiscal_ready')->default(false);
            $table->jsonb('fiscal_readiness_errors')->default('[]');
            $table->text('notes')->nullable();
            $table->integer('lock_version')->default(1);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'invoice_number'], 'invoices_org_number_unique');
            $table->index(['organization_id', 'status', 'business_date']);
            $table->index(['customer_id']);
        });

        // 2. Invoice Items
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->integer('line_number');
            $table->foreignId('tariff_version_id')->constrained('tariff_versions')->onDelete('restrict');
            $table->string('description', 255);
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_rate', 12, 4);
            $table->decimal('base_gross_amount', 14, 2);
            $table->decimal('fuel_surcharge_amount', 14, 2)->default(0.00);
            $table->decimal('gross_amount', 14, 2);
            $table->decimal('ppa_amount', 14, 2)->default(0.00);
            $table->decimal('discount_amount', 14, 2)->default(0.00);
            $table->decimal('net_amount', 14, 2);
            $table->decimal('tax_amount', 14, 2)->default(0.00);
            $table->decimal('total_charge_amount', 14, 2);
            $table->timestampsTz();

            $table->unique(['invoice_id', 'line_number']);
        });

        // 3. Invoice Item Pricing Snapshots (Frozen calculation provenance)
        Schema::create('invoice_item_pricing_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_item_id')->constrained('invoice_items')->cascadeOnDelete();
            $table->foreignId('tariff_version_id')->constrained('tariff_versions')->onDelete('restrict');
            $table->string('tariff_code', 64);
            $table->string('service_type', 32);
            $table->string('route_type', 32);
            $table->string('tax_treatment_key', 32);
            $table->string('ppa_share_applicability', 32);
            $table->decimal('ppa_share_rate', 6, 4)->default(0.0000);
            $table->string('fuel_surcharge_applicability', 32);
            $table->foreignId('fuel_price_observation_id')->nullable()->constrained('fuel_price_observations')->nullOnDelete();
            $table->decimal('fuel_price', 12, 4)->nullable();
            $table->foreignId('fuel_band_id')->nullable()->constrained('fuel_surcharge_bands')->nullOnDelete();
            $table->decimal('fuel_surcharge_percent', 6, 4)->nullable();
            $table->jsonb('calculation_payload');
            $table->timestampsTz();

            $table->unique(['invoice_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_item_pricing_snapshots');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
