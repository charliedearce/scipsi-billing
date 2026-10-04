<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('legacy_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('source_key', 64);
            $table->string('package_hash', 64);
            $table->string('status', 24)->default('STAGING');
            $table->string('private_path');
            $table->unsignedBigInteger('byte_offset')->default(0);
            $table->unsignedBigInteger('processed_rows')->default(0);
            $table->unsignedBigInteger('expected_rows');
            $table->jsonb('manifest');
            $table->jsonb('summary')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('finalized_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestampsTz();
            $table->unique(['organization_id', 'package_hash']);
            $table->index(['organization_id', 'id']);
        });
        Schema::create('legacy_import_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('legacy_import_batches')->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('source_key', 64);
            $table->string('source_table', 32);
            $table->string('source_id', 64);
            $table->string('row_hash', 64);
            $table->string('disposition', 24)->default('STAGED');
            $table->string('reference', 100)->nullable();
            $table->string('related_reference', 100)->nullable();
            $table->string('account_number', 100)->nullable();
            $table->string('display_name', 255)->nullable();
            $table->string('source_date', 40)->nullable();
            $table->string('source_status', 32)->nullable();
            foreach (['gross', 'ppa', 'discount', 'net', 'tax', 'due', 'charge'] as $amount) {
                $table->decimal($amount.'_amount', 18, 2)->nullable();
            }
            $table->jsonb('payload');
            $table->jsonb('issues');
            $table->jsonb('reconciliation')->nullable();
            $table->timestampTz('created_at');
            $table->unique(['batch_id', 'source_table', 'source_id'], 'legacy_batch_row_unique');
            $table->index(['organization_id', 'source_key', 'source_table', 'reference'], 'legacy_reference_index');
            $table->index(['batch_id', 'source_table', 'reference'], 'legacy_batch_reference_index');
            $table->index(['organization_id', 'disposition', 'source_table', 'id'], 'legacy_history_index');
        });
        DB::statement("CREATE UNIQUE INDEX legacy_import_identity_unique ON legacy_import_records (organization_id, source_key, source_table, source_id) WHERE disposition = 'IMPORTED'");
        DB::statement("CREATE OR REPLACE FUNCTION protect_legacy_import_record() RETURNS trigger AS $$ BEGIN IF TG_OP = 'DELETE' OR OLD.disposition = 'IMPORTED' THEN RAISE EXCEPTION 'Legacy history is immutable'; END IF; RETURN NEW; END; $$ LANGUAGE plpgsql");
        DB::statement('CREATE TRIGGER legacy_import_record_immutable BEFORE UPDATE OR DELETE ON legacy_import_records FOR EACH ROW EXECUTE FUNCTION protect_legacy_import_record()');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_import_records');
        DB::statement('DROP FUNCTION IF EXISTS protect_legacy_import_record()');
        Schema::dropIfExists('legacy_import_batches');
    }
};
