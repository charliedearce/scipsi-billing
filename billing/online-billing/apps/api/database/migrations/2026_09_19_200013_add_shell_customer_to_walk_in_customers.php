<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add shell_customer_id to walk_in_customers.
     *
     * Design clarification:
     * - shell_customer_id: the internal Customer record created at walk-in time for invoice FK purposes.
     *   This is always present and never changes.
     * - customer_id: the portal Customer account that a user has claimed/linked after registration.
     *   Starts NULL and is set only on successful claim approval.
     *
     * Previously customer_id was being dual-purposed, which caused "already linked" false positives.
     */
    public function up(): void
    {
        Schema::table('walk_in_customers', function (Blueprint $table): void {
            $table->unsignedBigInteger('shell_customer_id')
                ->nullable()
                ->after('location_id');

            $table->foreign('shell_customer_id')
                ->references('id')
                ->on('customers')
                ->nullOnDelete();

            $table->index('shell_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('walk_in_customers', function (Blueprint $table): void {
            $table->dropForeign(['shell_customer_id']);
            $table->dropColumn('shell_customer_id');
        });
    }
};
