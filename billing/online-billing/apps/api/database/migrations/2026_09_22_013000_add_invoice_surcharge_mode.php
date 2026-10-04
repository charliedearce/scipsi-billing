<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // NONE = no surcharge; FUEL = W29 scheduled fuel bands;
            // DANGEROUS_CARGO = teller % of original tariff rate (factor, e.g. 1.5000 = 150%).
            // Mutually exclusive per bill (legacy FUEL SURCHARGE / DANGER radios).
            $table->string('surcharge_mode', 32)->default('FUEL')->after('notes');
            $table->decimal('dangerous_cargo_percent', 6, 4)->nullable()->after('surcharge_mode');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['surcharge_mode', 'dangerous_cargo_percent']);
        });
    }
};
