<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_request_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_request_id')->constrained('billing_requests')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('linked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('linked_at');
            $table->timestamps();

            $table->unique('invoice_id');
            $table->unique(['billing_request_id', 'invoice_id']);
            $table->index(['billing_request_id', 'linked_at']);
        });

        // Backfill historical 1:1 links so portal/teller reads stay consistent.
        $now = now();
        DB::table('billing_requests')
            ->whereNotNull('invoice_id')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($now): void {
                $inserts = [];
                foreach ($rows as $row) {
                    $inserts[] = [
                        'billing_request_id' => $row->id,
                        'invoice_id' => $row->invoice_id,
                        'linked_by_user_id' => $row->assigned_to_user_id,
                        'linked_at' => $row->updated_at ?? $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if ($inserts !== []) {
                    DB::table('billing_request_invoices')->insertOrIgnore($inserts);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_request_invoices');
    }
};
