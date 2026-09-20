<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariff_versions', function (Blueprint $table): void {
            $table->unsignedInteger('lock_version')->default(1);
            $table->text('publication_reason')->nullable();
        });

        Schema::table('fuel_price_observations', function (Blueprint $table): void {
            $table->string('scope_key', 64)->default('ORGANIZATION');
            $table->string('source_reference', 255)->nullable();
            $table->string('source_evidence_ref', 255)->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->index(['organization_id', 'scope_key', 'product_grade', 'currency', 'unit_of_measure', 'effective_at'], 'fuel_observation_resolution_idx');
        });

        Schema::table('fuel_surcharge_policy_versions', function (Blueprint $table): void {
            $table->string('scope_key', 64)->default('ORGANIZATION');
            $table->string('fuel_price_source', 64)->default('LATEST_EFFECTIVE');
            $table->string('fuel_currency', 3)->default('PHP');
            $table->string('fuel_unit_of_measure', 32)->default('LITER');
            $table->string('rounding_mode', 16)->default('T2');
            $table->unsignedInteger('lock_version')->default(1);
            $table->text('publication_reason')->nullable();
            $table->index(['organization_id', 'scope_key', 'status', 'effective_from'], 'fuel_policy_resolution_idx');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_surcharge_policy_versions', function (Blueprint $table): void {
            $table->dropIndex('fuel_policy_resolution_idx');
            $table->dropColumn([
                'scope_key',
                'fuel_price_source',
                'fuel_currency',
                'fuel_unit_of_measure',
                'rounding_mode',
                'lock_version',
                'publication_reason',
            ]);
        });

        Schema::table('fuel_price_observations', function (Blueprint $table): void {
            $table->dropIndex('fuel_observation_resolution_idx');
            $table->dropForeign(['reviewed_by_user_id']);
            $table->dropColumn([
                'scope_key',
                'source_reference',
                'source_evidence_ref',
                'reviewed_by_user_id',
                'lock_version',
            ]);
        });

        Schema::table('tariff_versions', function (Blueprint $table): void {
            $table->dropColumn(['lock_version', 'publication_reason']);
        });
    }
};
