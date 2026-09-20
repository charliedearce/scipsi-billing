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
        // 1. Named Document Types
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->string('code', 80);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('purpose', 40); // BILLING_SUPPORT, WITHHOLDING_CERTIFICATE, EXEMPTION_EVIDENCE, PAYMENT_PROOF
            $table->jsonb('allowed_mime_types')->default('["application/pdf", "image/jpeg", "image/png"]');
            $table->integer('max_file_size_kb')->default(10240); // 10MB default
            $table->integer('max_files')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'doc_types_org_code_unique');
            $table->index(['organization_id', 'purpose', 'is_active'], 'doc_types_purpose_active_idx');
        });

        // 2. Versioned Service/Location Document Requirements
        Schema::create('document_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->foreignId('location_id')->nullable()->constrained('locations')->onDelete('restrict');
            $table->string('service_type', 80); // CARGO_HANDLING, BERTHING_DUES, GENERAL
            $table->foreignId('document_type_id')->constrained('document_types')->onDelete('restrict');
            $table->boolean('is_required')->default(true);
            $table->timestampTz('effective_from')->nullable();
            $table->timestampTz('effective_to')->nullable();
            $table->integer('version')->default(1);
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->index(['organization_id', 'service_type', 'is_required'], 'doc_req_org_service_req_idx');
            $table->index(['location_id', 'service_type'], 'doc_req_loc_service_idx');
        });

        // 3. Private Files Aggregate Root
        Schema::create('private_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->foreignId('location_id')->nullable()->constrained('locations')->onDelete('restrict');
            $table->foreignId('document_type_id')->constrained('document_types')->onDelete('restrict');
            $table->string('purpose', 40);
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('owner_id')->nullable()->constrained('users')->onDelete('restrict');
            $table->integer('current_version')->default(1);
            $table->string('status', 40)->default('PENDING_SCAN'); // PENDING_SCAN, CLEAN, QUARANTINED, REPLACED, REJECTED
            $table->timestamps();

            $table->index(['organization_id', 'owner_id', 'purpose'], 'private_files_owner_purpose_idx');
            $table->index(['document_type_id', 'status'], 'private_files_type_status_idx');
        });

        // 4. Private File Versions (Immutable individual files on private disk)
        Schema::create('private_file_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('private_file_id')->constrained('private_files')->onDelete('restrict');
            $table->integer('version_number');
            $table->string('disk', 40)->default('local_private');
            $table->text('file_path');
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('sha256_checksum', 64);
            $table->string('scan_status', 40)->default('PENDING'); // PENDING, CLEAN, QUARANTINED
            $table->jsonb('scan_details')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('restrict');
            $table->text('replacement_reason')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['private_file_id', 'version_number'], 'pfv_file_id_version_unique');
            $table->index(['sha256_checksum'], 'pfv_checksum_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('private_file_versions');
        Schema::dropIfExists('private_files');
        Schema::dropIfExists('document_requirements');
        Schema::dropIfExists('document_types');
    }
};
