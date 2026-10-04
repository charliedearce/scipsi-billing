<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Repair document reviews that progressed to billing before acceptance was persisted.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE billing_request_documents AS document
            SET review_status = 'ACCEPTED',
                reviewed_version_number = private_file.current_version,
                rejection_reason = NULL,
                updated_at = CURRENT_TIMESTAMP
            FROM billing_requests AS request,
                 private_files AS private_file
            WHERE document.billing_request_id = request.id
              AND document.private_file_id = private_file.id
              AND document.review_status = 'PENDING'
              AND request.status IN ('BILLING_IN_PROGRESS', 'BILL_READY', 'CLOSED')
        SQL);
    }

    /**
     * The prior pending values were incomplete workflow data and cannot be restored reliably.
     */
    public function down(): void
    {
        // Intentionally irreversible data correction.
    }
};
