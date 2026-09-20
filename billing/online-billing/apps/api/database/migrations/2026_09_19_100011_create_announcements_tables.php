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
        // 1. Announcements header table
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('creator_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('draft')->index(); // draft, scheduled, published, expired, retired
            $table->unsignedBigInteger('current_version_id')->nullable()->index();
            $table->foreignId('retired_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->text('retirement_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        // 2. Immutable announcement versions table
        Schema::create('announcement_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->string('title', 255);
            $table->text('body');
            $table->string('severity', 20)->default('INFO'); // INFO, MAINTENANCE, IMPORTANT, CRITICAL
            $table->string('audience_type', 20)->default('all'); // all, targeted
            $table->timestamp('effective_start_at')->index();
            $table->timestamp('effective_end_at')->nullable()->index();
            $table->boolean('is_dismissible')->default(true);
            $table->text('change_reason')->nullable();
            $table->string('content_hash', 64);
            $table->foreignId('author_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['announcement_id', 'version_number']);
            $table->index(['effective_start_at', 'effective_end_at']);
        });

        // 3. Announcement audience roles
        Schema::create('announcement_audience_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_version_id')->constrained('announcement_versions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['announcement_version_id', 'role_id'], 'announcement_version_role_unique');
        });

        // 4. Announcement audience locations
        Schema::create('announcement_audience_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_version_id')->constrained('announcement_versions')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['announcement_version_id', 'location_id'], 'announcement_version_location_unique');
        });

        // 5. Per-user announcement state tracking
        Schema::create('announcement_user_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_version_id')->constrained('announcement_versions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['announcement_version_id', 'user_id'], 'announcement_user_state_unique');
            $table->index(['user_id', 'dismissed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_user_states');
        Schema::dropIfExists('announcement_audience_locations');
        Schema::dropIfExists('announcement_audience_roles');
        Schema::dropIfExists('announcement_versions');
        Schema::dropIfExists('announcements');
    }
};
