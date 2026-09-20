<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add walk-in customer reference to invoices.
     * Walk-in invoices are created by tellers without a portal login.
     * The FK is nullable because most invoices originate from registered portal customers.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('walk_in_customer_id')
                ->nullable()
                ->after('buyer_profile_version_id');

            $table->foreign('walk_in_customer_id')
                ->references('id')
                ->on('walk_in_customers')
                ->nullOnDelete();

            $table->index('walk_in_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['walk_in_customer_id']);
            $table->dropColumn('walk_in_customer_id');
        });
    }
};
