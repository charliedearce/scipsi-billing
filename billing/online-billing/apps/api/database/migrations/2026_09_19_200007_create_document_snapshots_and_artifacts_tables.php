<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 32); // INVOICE, RECEIPT
            $table->unsignedBigInteger('document_id');
            $table->string('document_kind', 32); // SERVICE, SERVICE_NSCL, PPA, COLLECTION_RECEIPT
            $table->foreignId('template_version_id')->constrained('document_template_versions');
            $table->jsonb('routing_metadata')->nullable();
            $table->jsonb('payload_snapshot');
            $table->string('renderer_version', 32)->default('dompdf 3.1.6');
            $table->timestamps();

            $table->index(['organization_id', 'document_type', 'document_id'], 'doc_snap_org_type_id_idx');
        });

        Schema::create('document_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('snapshot_id')->constrained('document_snapshots')->cascadeOnDelete();
            $table->string('document_type', 32);
            $table->unsignedBigInteger('document_id');
            $table->string('artifact_type', 32)->default('CANONICAL_PDF');
            $table->string('file_path', 255);
            $table->bigInteger('file_size_bytes')->default(0);
            $table->string('sha256_hash', 64)->default('');
            $table->string('mime_type', 64)->default('application/pdf');
            $table->string('status', 20)->default('RENDERED'); // RENDERED, FAILED
            $table->text('error_message')->nullable();
            $table->timestampTz('rendered_at')->nullable();
            $table->timestamps();

            $table->unique(['snapshot_id', 'artifact_type']);
            $table->index(['organization_id', 'document_type', 'document_id', 'status'], 'doc_art_org_type_id_status_idx');
        });

        Schema::create('print_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artifact_id')->constrained('document_artifacts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('print_type', 20)->default('ORIGINAL'); // ORIGINAL, REPRINT
            $table->string('printer_profile', 64)->nullable();
            $table->string('paper_size', 32)->nullable();
            $table->boolean('is_reprint')->default(false);
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->index(['artifact_id', 'created_at']);
        });

        if (! Schema::hasColumn('invoice_items', 'cargo_code')) {
            Schema::table('invoice_items', function (Blueprint $table): void {
                $table->string('cargo_code', 64)->nullable()->after('line_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoice_items', 'cargo_code')) {
            Schema::table('invoice_items', function (Blueprint $table): void {
                $table->dropColumn('cargo_code');
            });
        }
        Schema::dropIfExists('print_attempts');
        Schema::dropIfExists('document_artifacts');
        Schema::dropIfExists('document_snapshots');
    }
};
