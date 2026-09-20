<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_groups', function (Blueprint $table): void {
            $table->unsignedInteger('review_target_hours_snapshot')->nullable()->after('manual_deadline_hours_snapshot');
            $table->unsignedInteger('clearance_target_hours_snapshot')->nullable()->after('review_target_hours_snapshot');
            $table->unsignedInteger('correction_window_hours_snapshot')->nullable()->after('clearance_target_hours_snapshot');
            $table->timestampTz('review_due_at')->nullable()->after('payment_deadline_at');
            $table->timestampTz('clearance_due_at')->nullable()->after('review_due_at');
            $table->timestampTz('correction_due_at')->nullable()->after('clearance_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('payment_groups', function (Blueprint $table): void {
            $table->dropColumn([
                'review_target_hours_snapshot',
                'clearance_target_hours_snapshot',
                'correction_window_hours_snapshot',
                'review_due_at',
                'clearance_due_at',
                'correction_due_at',
            ]);
        });
    }
};
