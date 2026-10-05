<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_policy_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('storage_path');
            $table->string('mime_type', 40);
            $table->unsignedInteger('byte_size');
            $table->char('sha256', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['organization_id', 'sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_policy_images');
    }
};
