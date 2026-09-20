<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_event_locations', function (Blueprint $table): void {
            $table->foreignId('notification_event_id')
                ->constrained('notification_events')
                ->onDelete('cascade');
            $table->foreignId('location_id')
                ->constrained('locations')
                ->onDelete('restrict');

            $table->primary(['notification_event_id', 'location_id']);
            $table->index(['location_id', 'notification_event_id'], 'notification_event_locations_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_event_locations');
    }
};
