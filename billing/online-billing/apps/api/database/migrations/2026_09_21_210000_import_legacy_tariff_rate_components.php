<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->dropUnique('tariffs_organization_id_tariff_code_unique');
            $table->string('legacy_t_scode', 32)->nullable()->after('unit_of_measure');
            $table->string('legacy_t_sname', 128)->nullable()->after('legacy_t_scode');
            $table->string('cargo_class', 64)->nullable()->after('legacy_t_sname');
            $table->unique(
                ['organization_id', 'tariff_code', 'service_type', 'route_type'],
                'tariffs_org_code_service_route_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->dropUnique('tariffs_org_code_service_route_unique');
            $table->dropColumn(['legacy_t_scode', 'legacy_t_sname', 'cargo_class']);
            $table->unique(['organization_id', 'tariff_code']);
        });
    }
};
