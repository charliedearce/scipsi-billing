<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->index(['organization_id', 'created_at'], 'notification_deliveries_org_created_idx');
        });

        Schema::table('announcement_user_states', function (Blueprint $table): void {
            $table->index(['announcement_version_id', 'seen_at'], 'announcement_states_version_seen_idx');
            $table->index(['announcement_version_id', 'acknowledged_at'], 'announcement_states_version_ack_idx');
            $table->index(['announcement_version_id', 'dismissed_at'], 'announcement_states_version_dismissed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('announcement_user_states', function (Blueprint $table): void {
            $table->dropIndex('announcement_states_version_seen_idx');
            $table->dropIndex('announcement_states_version_ack_idx');
            $table->dropIndex('announcement_states_version_dismissed_idx');
        });

        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->dropIndex('notification_deliveries_org_created_idx');
        });
    }
};
