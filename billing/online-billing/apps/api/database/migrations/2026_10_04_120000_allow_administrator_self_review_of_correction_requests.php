<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE document_correction_requests DROP CONSTRAINT IF EXISTS correction_request_distinct_reviewer');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE document_correction_requests ADD CONSTRAINT correction_request_distinct_reviewer CHECK (reviewed_by_user_id IS NULL OR reviewed_by_user_id <> requested_by_user_id) NOT VALID');
    }
};
