<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_withholding_certificates', function (Blueprint $table) {
            $table->decimal('withholding_rate', 6, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customer_withholding_certificates', function (Blueprint $table) {
            $table->decimal('withholding_rate', 6, 4)->nullable(false)->default(0.0100)->change();
        });
    }
};
