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
        Schema::table('credit_policy_versions', function (Blueprint $table): void {
            $table->unsignedInteger('review_target_hours')->default(24);
        });
        Schema::table('vip_credit_repayment_submissions', function (Blueprint $table): void {
            $table->unsignedInteger('review_target_hours_snapshot')->default(24);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vip_credit_repayment_submissions', function (Blueprint $table): void {
            $table->dropColumn('review_target_hours_snapshot');
        });
        Schema::table('credit_policy_versions', function (Blueprint $table): void {
            $table->dropColumn('review_target_hours');
        });
    }
};
