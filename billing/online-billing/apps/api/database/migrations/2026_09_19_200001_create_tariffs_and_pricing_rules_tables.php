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
        // 1. Tariffs Master
        Schema::create('tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('tariff_code', 64);
            $table->string('name', 255);
            $table->string('service_type', 32); // ARRASTRE, STEVEDORING, OTHER
            $table->string('route_type', 32); // DOMESTIC, FOREIGN
            $table->string('unit_of_measure', 32)->default('REV_TON');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['organization_id', 'tariff_code']);
            $table->index(['organization_id', 'service_type', 'route_type']);
        });

        // 2. Tariff Versions (Effective-dated pricing rules & classifications)
        Schema::create('tariff_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_id')->constrained('tariffs')->cascadeOnDelete();
            $table->integer('version_number')->default(1);
            $table->decimal('rate', 12, 4);
            $table->string('tax_treatment_key', 32)->default('VATABLE'); // VATABLE, EXEMPT, ZERO_RATED
            $table->string('ppa_share_applicability', 32)->default('NOT_APPLICABLE'); // NOT_APPLICABLE, APPLICABLE
            $table->decimal('ppa_share_rate', 6, 4)->default(0.0000);
            $table->string('fuel_surcharge_applicability', 32)->default('NOT_APPLICABLE'); // NOT_APPLICABLE, APPLICABLE
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->string('status', 32)->default('effective'); // draft, published, effective, retired
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['tariff_id', 'version_number']);
            $table->index(['tariff_id', 'status', 'effective_from']);
        });

        // 3. Fuel Price Observations
        Schema::create('fuel_price_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('product_grade', 64)->default('DIESEL');
            $table->decimal('price', 12, 4);
            $table->string('currency', 3)->default('PHP');
            $table->string('unit_of_measure', 32)->default('LITER');
            $table->timestampTz('observed_at');
            $table->timestampTz('effective_at');
            $table->string('status', 32)->default('active'); // active, retired
            $table->foreignId('entered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['organization_id', 'status', 'effective_at']);
        });

        // 4. Fuel Surcharge Policy Versions
        Schema::create('fuel_surcharge_policy_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->integer('version_number')->default(1);
            $table->string('basis', 64)->default('BASE_TARIFF_AMOUNT');
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->string('status', 32)->default('effective'); // draft, published, effective, retired
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['organization_id', 'version_number']);
            $table->index(['organization_id', 'status', 'effective_from']);
        });

        // 5. Fuel Surcharge Bands
        Schema::create('fuel_surcharge_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_version_id')->constrained('fuel_surcharge_policy_versions')->cascadeOnDelete();
            $table->decimal('min_price', 12, 4);
            $table->decimal('max_price', 12, 4)->nullable(); // null means open upper bound
            $table->decimal('surcharge_percent', 6, 4);
            $table->string('label', 100);
            $table->timestampsTz();

            $table->index(['policy_version_id', 'min_price']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fuel_surcharge_bands');
        Schema::dropIfExists('fuel_surcharge_policy_versions');
        Schema::dropIfExists('fuel_price_observations');
        Schema::dropIfExists('tariff_versions');
        Schema::dropIfExists('tariffs');
    }
};
