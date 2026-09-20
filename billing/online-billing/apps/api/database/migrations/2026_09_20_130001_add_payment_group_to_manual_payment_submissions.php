<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_payment_submissions', function (Blueprint $table): void {
            $table->foreignId('payment_group_id')
                ->nullable()
                ->unique()
                ->after('customer_id')
                ->constrained('payment_groups')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('manual_payment_submissions', function (Blueprint $table): void {
            $table->dropForeign(['payment_group_id']);
            $table->dropUnique(['payment_group_id']);
            $table->dropColumn('payment_group_id');
        });
    }
};
