<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('document_kind', 32); // SERVICE, SERVICE_NSCL, PPA, COLLECTION_RECEIPT
            $table->string('code', 64);
            $table->string('name', 128);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'document_kind']);
        });

        Schema::create('document_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('document_templates')->cascadeOnDelete();
            $table->integer('version_number');
            $table->string('status', 20)->default('DRAFT'); // DRAFT, VALIDATED, PUBLISHED, RETIRED
            $table->string('layout_schema_version', 16)->default('1.0.0');
            $table->jsonb('layout_definition');
            $table->jsonb('validation_summary')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('retired_at')->nullable();
            $table->timestamps();

            $table->unique(['template_id', 'version_number']);
            $table->index(['template_id', 'status']);
        });

        Schema::create('document_template_activations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('document_kind', 32);
            $table->foreignId('template_version_id')->constrained('document_template_versions')->cascadeOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('series_id')->nullable()->constrained('document_series')->nullOnDelete();
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'document_kind', 'is_active', 'effective_from'], 'doc_tpl_act_org_kind_active_eff_idx');
        });

        Schema::create('document_template_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('asset_type', 32); // LOGO, WATERMARK, SIGNATURE
            $table->string('name', 128);
            $table->string('file_path', 255);
            $table->string('mime_type', 64);
            $table->bigInteger('file_size_bytes');
            $table->string('sha256_hash', 64);
            $table->integer('width_px')->nullable();
            $table->integer('height_px')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'asset_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_template_assets');
        Schema::dropIfExists('document_template_activations');
        Schema::dropIfExists('document_template_versions');
        Schema::dropIfExists('document_templates');
    }
};
