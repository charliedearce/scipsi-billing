<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->string('period_code', 64);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 16)->default('OPEN'); // OPEN, CLOSED
            $table->timestampTz('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['organization_id', 'period_code']);
            $table->index(['organization_id', 'status', 'starts_on', 'ends_on']);
        });

        Schema::create('backdate_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('restrict');
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('accounting_period_id')->constrained('accounting_periods')->onDelete('restrict');
            $table->string('document_type', 32); // INVOICE, RECEIPT
            $table->date('business_date');
            $table->string('status', 16)->default('PENDING'); // PENDING, APPROVED, REJECTED, CONSUMED, EXPIRED
            $table->text('reason');
            $table->foreignId('requested_by_user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('consumed_at')->nullable();
            $table->string('consumed_document_type', 32)->nullable();
            $table->unsignedBigInteger('consumed_document_id')->nullable();
            $table->foreignId('consumed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['organization_id', 'status', 'document_type', 'business_date']);
            $table->index(['requested_by_user_id', 'status']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('accounting_period_id')->nullable()->after('business_date')->constrained('accounting_periods')->onDelete('restrict');
            $table->foreignId('backdate_authorization_id')->nullable()->after('accounting_period_id')->unique()->constrained('backdate_authorizations')->onDelete('restrict');
        });

        Schema::table('receipts', function (Blueprint $table): void {
            $table->foreignId('accounting_period_id')->nullable()->after('business_date')->constrained('accounting_periods')->onDelete('restrict');
            $table->foreignId('backdate_authorization_id')->nullable()->after('accounting_period_id')->unique()->constrained('backdate_authorizations')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('backdate_authorization_id');
            $table->dropConstrainedForeignId('accounting_period_id');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('backdate_authorization_id');
            $table->dropConstrainedForeignId('accounting_period_id');
        });

        Schema::dropIfExists('backdate_authorizations');
        Schema::dropIfExists('accounting_periods');
    }
};
