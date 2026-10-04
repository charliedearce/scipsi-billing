<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vessels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('name', 80);
            $table->string('vessel_type', 64)->nullable();
            $table->string('typical_route', 16)->nullable();
            $table->string('shipping_line', 128)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['organization_id', 'name']);
            $table->index(['organization_id', 'is_active', 'name']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('vessel_id')->nullable()->after('customer_id')->constrained('vessels')->restrictOnDelete();
            $table->string('vessel_name', 80)->nullable()->after('vessel_id');
            $table->string('voyage', 10)->nullable()->after('vessel_name');
            $table->string('movement_type', 8)->nullable()->after('voyage');
            $table->string('route_type', 16)->nullable()->after('movement_type');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vessel_id');
            $table->dropColumn(['vessel_name', 'voyage', 'movement_type', 'route_type']);
        });
        Schema::dropIfExists('vessels');
    }
};
