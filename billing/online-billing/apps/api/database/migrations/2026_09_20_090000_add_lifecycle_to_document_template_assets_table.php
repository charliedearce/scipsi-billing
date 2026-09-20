<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_template_assets', function (Blueprint $table): void {
            $table->string('status', 20)->default('ACTIVE')->after('height_px');
            $table->foreignId('uploaded_by_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestampTz('retired_at')->nullable()->after('uploaded_by_user_id');
            $table->foreignId('retired_by_user_id')->nullable()->after('retired_at')->constrained('users')->nullOnDelete();
            $table->text('retirement_reason')->nullable()->after('retired_by_user_id');

            $table->index(['organization_id', 'status'], 'doc_tpl_asset_org_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('document_template_assets', function (Blueprint $table): void {
            $table->dropIndex('doc_tpl_asset_org_status_idx');
            $table->dropConstrainedForeignId('retired_by_user_id');
            $table->dropColumn(['retired_at', 'retirement_reason']);
            $table->dropConstrainedForeignId('uploaded_by_user_id');
            $table->dropColumn('status');
        });
    }
};
