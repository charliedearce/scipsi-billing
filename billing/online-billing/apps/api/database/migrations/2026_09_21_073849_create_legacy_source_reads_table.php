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
        Schema::create('legacy_source_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('request_key');
            $table->string('source_key', 64);
            $table->string('status', 24)->default('QUEUED');
            $table->text('encrypted_connection')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->unsignedBigInteger('read_rows')->default(0);
            $table->unsignedBigInteger('expected_rows')->nullable();
            $table->foreignId('batch_id')->nullable()->constrained('legacy_import_batches')->restrictOnDelete();
            $table->string('error_message', 500)->nullable();
            $table->timestampsTz();
            $table->unique(['organization_id', 'request_key']);
            $table->index(['organization_id', 'id']);
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_source_reads');
    }
};
