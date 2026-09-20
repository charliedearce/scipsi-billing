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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject', 255);
            $table->string('context_type', 64)->nullable();
            $table->unsignedBigInteger('context_id')->nullable();
            $table->string('status', 32)->default('open');
            $table->timestampTz('last_message_at')->nullable();
            $table->timestampsTz();

            $table->index(['organization_id', 'status']);
            $table->index(['customer_id']);
            $table->index(['context_type', 'context_id']);
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32)->default('participant');
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestampTz('last_read_at')->nullable();
            $table->timestampsTz();

            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('message_type', 32)->default('customer');
            $table->text('body');
            $table->jsonb('attachments')->nullable();
            $table->timestampsTz();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['sender_id']);
            $table->index(['conversation_id', 'message_type']);
        });

        Schema::create('in_app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('event_id')->nullable();
            $table->string('type', 64);
            $table->string('title', 255);
            $table->text('body');
            $table->jsonb('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'is_read', 'created_at']);
            $table->index(['event_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('in_app_notifications');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
