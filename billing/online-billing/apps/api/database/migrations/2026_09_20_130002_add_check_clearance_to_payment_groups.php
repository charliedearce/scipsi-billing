<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_groups', function (Blueprint $table): void {
            $table->string('payment_method', 32)->default('BANK_TRANSFER')->after('route');
            $table->string('check_clearance_status', 32)->default('NOT_APPLICABLE')->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('payment_groups', function (Blueprint $table): void {
            $table->dropColumn(['payment_method', 'check_clearance_status']);
        });
    }
};
