<?php

use App\Http\Controllers\Api\V1\AccountingPeriodController;
use App\Http\Controllers\Api\V1\AccountStatementController;
use App\Http\Controllers\Api\V1\AdminDashboardController;
use App\Http\Controllers\Api\V1\AdminDocumentSeriesController;
use App\Http\Controllers\Api\V1\AdminTariffController;
use App\Http\Controllers\Api\V1\AdminTaxEvidenceController;
use App\Http\Controllers\Api\V1\AnnouncementAdminController;
use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\AuditEventController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BackdateAuthorizationController;
use App\Http\Controllers\Api\V1\BillClaimController;
use App\Http\Controllers\Api\V1\BillingCollectionsReportController;
use App\Http\Controllers\Api\V1\CommunicationOperationsReportController;
use App\Http\Controllers\Api\V1\ContactVerificationController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\CustomerAccountAdminController;
use App\Http\Controllers\Api\V1\CustomerBillingRequestController;
use App\Http\Controllers\Api\V1\CustomerProfileController;
use App\Http\Controllers\Api\V1\CustomerRegistrationController;
use App\Http\Controllers\Api\V1\CustomerTaxEvidenceController;
use App\Http\Controllers\Api\V1\DocumentCorrectionRequestController;
use App\Http\Controllers\Api\V1\DocumentRequirementController;
use App\Http\Controllers\Api\V1\DocumentRevisionController;
use App\Http\Controllers\Api\V1\DocumentSeriesController;
use App\Http\Controllers\Api\V1\DocumentStudioController;
use App\Http\Controllers\Api\V1\DocumentTemplateAssetController;
use App\Http\Controllers\Api\V1\DocumentTypeController;
use App\Http\Controllers\Api\V1\FiscalInvoiceController;
use App\Http\Controllers\Api\V1\FuelSurchargeController;
use App\Http\Controllers\Api\V1\InvoiceArtifactController;
use App\Http\Controllers\Api\V1\InvoiceDraftController;
use App\Http\Controllers\Api\V1\LateChargeController;
use App\Http\Controllers\Api\V1\LegacyImportController;
use App\Http\Controllers\Api\V1\LegacySqlImportController;
use App\Http\Controllers\Api\V1\ManualPaymentProofController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\OperationalSnapshotArtifactController;
use App\Http\Controllers\Api\V1\PaymentPolicyController;
use App\Http\Controllers\Api\V1\PpaClearanceController;
use App\Http\Controllers\Api\V1\PpaShareReportController;
use App\Http\Controllers\Api\V1\PrivateFileController;
use App\Http\Controllers\Api\V1\RealtimeController;
use App\Http\Controllers\Api\V1\ReceiptArtifactController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\SmsDeliveryController;
use App\Http\Controllers\Api\V1\SmsPolicyController;
use App\Http\Controllers\Api\V1\SmsTemplateController;
use App\Http\Controllers\Api\V1\TariffController;
use App\Http\Controllers\Api\V1\TellerClaimReviewController;
use App\Http\Controllers\Api\V1\TellerQueueController;
use App\Http\Controllers\Api\V1\TransmittalController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\VesselController;
use App\Http\Controllers\Api\V1\VipCreditAgingController;
use App\Http\Controllers\Api\V1\VipCreditController;
use App\Http\Controllers\Api\V1\VipPrincipalAgingReportController;
use App\Http\Controllers\Api\V1\WalkInBillingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Public health check & realtime config
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'scipsi-online-billing-api',
            'version' => 'v1',
        ]);
    });
    Route::get('/realtime/config', [RealtimeController::class, 'config']);

    // Authentication & Customer Self-Registration (public)
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('idempotent');
    Route::post('/auth/register', [CustomerRegistrationController::class, 'register'])->middleware('idempotent');
    Route::post('/auth/contact-verifications/mobile/send', [ContactVerificationController::class, 'send'])->middleware('idempotent');
    Route::post('/auth/contact-verifications/mobile/verify', [ContactVerificationController::class, 'verify'])->middleware('idempotent');

    // Authenticated & Active Protected Routes
    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        // Auth profile & session control
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/revoke-sessions', [AuthController::class, 'revokeSessions']);

        // Archive-only legacy imports. Every action additionally requires the Administrator role.
        Route::prefix('admin/legacy-imports')->group(function (): void {
            Route::get('/sql-server', [LegacySqlImportController::class, 'index']);
            Route::post('/sql-server/test', [LegacySqlImportController::class, 'test'])->middleware('throttle:10,1');
            Route::post('/sql-server/start', [LegacySqlImportController::class, 'start'])->middleware('throttle:10,1');
            Route::post('/sql-server/{id}/cancel', [LegacySqlImportController::class, 'cancel'])->whereNumber('id');
            Route::get('/', [LegacyImportController::class, 'index']);
            Route::get('/schema', [LegacyImportController::class, 'schema']);
            Route::get('/toolkit', [LegacyImportController::class, 'toolkit']);
            Route::get('/records', [LegacyImportController::class, 'records']);
            Route::get('/records/{id}', [LegacyImportController::class, 'record'])->whereNumber('id');
            Route::post('/', [LegacyImportController::class, 'store'])->middleware('throttle:10,1');
            Route::get('/{id}', [LegacyImportController::class, 'show'])->whereNumber('id');
            Route::post('/{id}/advance', [LegacyImportController::class, 'advance'])->whereNumber('id');
            Route::post('/{id}/finalize', [LegacyImportController::class, 'finalize'])->whereNumber('id');
        });

        // Roles & Permissions
        Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles:read');
        Route::get('/permissions', [RoleController::class, 'permissions'])->middleware('permission:roles:read');

        // User Administration
        Route::get('/users', [UserController::class, 'index'])->middleware('permission:users:read');
        Route::post('/users', [UserController::class, 'store'])->middleware(['permission:users:create', 'idempotent']);
        Route::get('/users/{id}', [UserController::class, 'show'])->middleware('permission:users:read');
        Route::put('/users/{id}', [UserController::class, 'update'])->middleware(['permission:users:update', 'idempotent']);
        Route::post('/users/{id}/suspend', [UserController::class, 'suspend'])->middleware(['permission:users:suspend', 'idempotent']);
        Route::post('/users/{id}/activate', [UserController::class, 'activate'])->middleware(['permission:users:activate', 'idempotent']);

        // Typed Application Settings
        Route::get('/settings', [SettingController::class, 'index'])->middleware('permission:settings:read');
        Route::get('/settings/{key}', [SettingController::class, 'show'])->middleware('permission:settings:read');
        Route::put('/settings/{key}', [SettingController::class, 'update'])->middleware(['permission:settings:update', 'idempotent']);

        // Audit Logs (legacy simple audit table)
        Route::get('/audit-logs', [AuditController::class, 'index'])->middleware('permission:audit:read');

        // P4-03 operational report register. This is not a fiscal/BIR export.
        Route::get('/admin/dashboard', AdminDashboardController::class)->middleware('permission:reports:read');
        Route::get('/reports/billing-collections', [BillingCollectionsReportController::class, 'index'])->middleware('permission:reports:read');
        Route::get('/reports/billing-collections/export', [BillingCollectionsReportController::class, 'export'])->middleware('permission:reports:export');
        Route::get('/reports/vip-credit-aging/export', [VipPrincipalAgingReportController::class, 'export'])->middleware('permission:credit_aging:export');
        Route::get('/reports/communications-operations', [CommunicationOperationsReportController::class, 'index'])->middleware('permission:sms_deliveries:view');

        // Document Revisions & Diff History (Decision W35 / P1-13)
        Route::get('/document-revisions/compare', [DocumentRevisionController::class, 'compare'])->middleware('permission:document_history:compare');
        Route::get('/document-revisions/{type}/{id}', [DocumentRevisionController::class, 'index'])->middleware('permission:document_history:view');
        Route::get('/document-revisions/{id}', [DocumentRevisionController::class, 'show'])->middleware('permission:document_history:view');

        // Business Audit Events (Decision W35 / P1-13)
        Route::get('/audit-events', [AuditEventController::class, 'index'])->middleware('permission:audit:read');
        Route::get('/audit-events/{id}', [AuditEventController::class, 'show'])->middleware('permission:audit:read');

        // Issued-document correction/reversal requests (P3-03): approval never edits a posted fact.
        Route::get('/document-correction-requests', [DocumentCorrectionRequestController::class, 'index'])->middleware('permission:corrections:request');
        Route::post('/document-correction-requests', [DocumentCorrectionRequestController::class, 'store'])->middleware(['permission:corrections:request', 'idempotent']);
        Route::post('/document-correction-requests/{id}/approve', [DocumentCorrectionRequestController::class, 'approve'])->middleware(['permission:corrections:approve', 'idempotent']);
        Route::post('/document-correction-requests/{id}/reject', [DocumentCorrectionRequestController::class, 'reject'])->middleware(['permission:corrections:approve', 'idempotent']);
        Route::post('/document-correction-requests/{id}/start-draft', [DocumentCorrectionRequestController::class, 'startDraft'])->middleware(['permission:billing:draft', 'idempotent']);
        Route::post('/document-correction-requests/{id}/execute', [DocumentCorrectionRequestController::class, 'execute'])->middleware(['idempotent']);

        // Document Types & Requirements (Decision W21 / P1-07)
        Route::get('/document-types', [DocumentTypeController::class, 'index'])->middleware('permission:documents:read');
        Route::post('/document-types', [DocumentTypeController::class, 'store'])->middleware(['permission:documents:manage', 'idempotent']);
        Route::get('/document-types/{id}', [DocumentTypeController::class, 'show'])->middleware('permission:documents:read');
        Route::put('/document-types/{id}', [DocumentTypeController::class, 'update'])->middleware(['permission:documents:manage', 'idempotent']);

        Route::get('/document-requirements', [DocumentRequirementController::class, 'index'])->middleware('permission:documents:read');
        Route::post('/document-requirements', [DocumentRequirementController::class, 'store'])->middleware(['permission:documents:manage', 'idempotent']);
        Route::get('/document-requirements/{id}', [DocumentRequirementController::class, 'show'])->middleware('permission:documents:read');
        Route::put('/document-requirements/{id}', [DocumentRequirementController::class, 'update'])->middleware(['permission:documents:manage', 'idempotent']);

        // Private Uploads & File Versions (Decision W21 / P1-07)
        Route::post('/files/upload', [PrivateFileController::class, 'upload'])->middleware(['permission:files:upload', 'idempotent']);
        Route::get('/files/{id}', [PrivateFileController::class, 'show'])->middleware('permission:files:view');
        Route::post('/files/{id}/replace', [PrivateFileController::class, 'replace'])->middleware(['permission:files:upload', 'idempotent']);
        Route::get('/files/{id}/download', [PrivateFileController::class, 'download'])->middleware('permission:files:view');
        Route::get('/files/{id}/download/{version}', [PrivateFileController::class, 'download'])->middleware('permission:files:view');
        Route::post('/files/{id}/quarantine', [PrivateFileController::class, 'quarantine'])->middleware(['permission:files:quarantine', 'idempotent']);

        // Realtime Broadcasting Channel Authorization
        Route::post('/broadcasting/auth', [RealtimeController::class, 'auth']);

        // Conversations & Chat (Decision W27 / P1-08)
        Route::get('/conversations', [ConversationController::class, 'index'])->middleware('permission:conversations:read');
        Route::post('/conversations', [ConversationController::class, 'store'])->middleware(['permission:conversations:create', 'idempotent']);
        Route::post('/conversations/for-billing-request/{billingRequestId}', [ConversationController::class, 'ensureForBillingRequest'])->middleware(['permission:conversations:create', 'idempotent']);
        Route::post('/conversations/for-bill-claim/{id}', [ConversationController::class, 'ensureForBillClaim'])->middleware('idempotent');
        Route::post('/conversations/for-invoice/{id}', [ConversationController::class, 'ensureForInvoice'])->middleware(['permission:conversations:create', 'idempotent']);
        Route::post('/conversations/for-receipt/{id}', [ConversationController::class, 'ensureForReceipt'])->middleware(['permission:conversations:create', 'idempotent']);
        Route::get('/conversations/{id}', [ConversationController::class, 'show'])->middleware('permission:conversations:read');
        Route::get('/conversations/{id}/messages', [ConversationController::class, 'messages'])->middleware('permission:conversations:read');
        Route::post('/conversations/{id}/messages', [ConversationController::class, 'sendMessage'])->middleware(['permission:conversations:send', 'idempotent']);
        Route::post('/conversations/{id}/read', [ConversationController::class, 'markRead'])->middleware('permission:conversations:read');
        Route::post('/conversations/{id}/participants', [ConversationController::class, 'addParticipant'])->middleware(['permission:conversations:assign', 'idempotent']);

        // In-App Notifications (Decision W27 / P1-08)
        Route::get('/notifications', [NotificationController::class, 'index'])->middleware('permission:notifications:read');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->middleware('permission:notifications:read');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->middleware('permission:notifications:read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->middleware('permission:notifications:read');

        // Transactional SMS Templates & Policies (Decision W31 / P1-09)
        Route::get('/sms/templates', [SmsTemplateController::class, 'index'])->middleware('permission:notification_templates:view');
        Route::post('/sms/templates', [SmsTemplateController::class, 'store'])->middleware(['permission:notification_templates:draft', 'idempotent']);
        Route::post('/sms/templates/preview', [SmsTemplateController::class, 'preview'])->middleware('permission:notification_templates:preview');
        Route::get('/sms/templates/{id}', [SmsTemplateController::class, 'show'])->middleware('permission:notification_templates:view');
        Route::post('/sms/templates/{id}/versions', [SmsTemplateController::class, 'storeVersion'])->middleware(['permission:notification_templates:draft', 'idempotent']);
        Route::post('/sms/templates/{id}/versions/{versionId}/publish', [SmsTemplateController::class, 'publish'])->middleware(['permission:notification_templates:publish', 'idempotent']);
        Route::post('/sms/templates/{id}/versions/{versionId}/activate', [SmsTemplateController::class, 'activate'])->middleware(['permission:notification_templates:activate', 'idempotent']);
        Route::post('/sms/templates/{id}/versions/{versionId}/retire', [SmsTemplateController::class, 'retire'])->middleware(['permission:notification_templates:publish', 'idempotent']);

        Route::get('/sms/policies', [SmsPolicyController::class, 'index'])->middleware('permission:notification_policies:manage');
        Route::put('/sms/policies/{id}', [SmsPolicyController::class, 'update'])->middleware(['permission:notification_policies:manage', 'idempotent']);

        // Transactional SMS Deliveries & Provider Observations (Decision W31 / P1-09)
        Route::get('/sms/provider/health', [SmsDeliveryController::class, 'health'])->middleware('permission:sms_provider:view');
        Route::get('/sms/deliveries', [SmsDeliveryController::class, 'index'])->middleware('permission:sms_deliveries:view');
        Route::get('/sms/deliveries/{id}', [SmsDeliveryController::class, 'show'])->middleware('permission:sms_deliveries:view');
        Route::post('/sms/deliveries/{id}/reconcile', [SmsDeliveryController::class, 'reconcile'])->middleware(['permission:sms_deliveries:reconcile', 'idempotent']);
        Route::post('/sms/deliveries/{id}/resend', [SmsDeliveryController::class, 'resend'])->middleware(['permission:sms_deliveries:resend', 'idempotent']);

        // Customer Notification Preferences (Decision W31 / P1-09)
        Route::get('/portal/notification-preferences', [NotificationPreferenceController::class, 'index'])->middleware('permission:notification_preferences:manage');
        Route::put('/portal/notification-preferences', [NotificationPreferenceController::class, 'update'])->middleware(['permission:notification_preferences:manage', 'idempotent']);

        // Customer Portal Profile & Buyer Profile (Decision W32 / P1-10)
        Route::get('/portal/profile', [CustomerProfileController::class, 'show']);
        Route::put('/portal/profile', [CustomerProfileController::class, 'update'])->middleware('idempotent');
        Route::post('/portal/profile/password', [CustomerProfileController::class, 'changePassword'])->middleware('idempotent');
        Route::post('/portal/profile/avatar', [CustomerProfileController::class, 'uploadAvatar'])->middleware('idempotent');

        // Admin Customer Accounts & Buyer Profiles (Decision W32 / P1-10)
        Route::get('/admin/customers', [CustomerAccountAdminController::class, 'index'])->middleware('permission:customer_accounts:view');
        Route::get('/admin/customers/next-account-number', [CustomerAccountAdminController::class, 'nextAccountNumber'])->middleware('permission:customer_accounts:manage');
        Route::post('/admin/customers', [CustomerAccountAdminController::class, 'store'])->middleware(['permission:customer_accounts:manage', 'idempotent']);
        Route::get('/admin/customers/{id}', [CustomerAccountAdminController::class, 'show'])->middleware('permission:customer_accounts:view');
        Route::put('/admin/customers/{id}', [CustomerAccountAdminController::class, 'update'])->middleware(['permission:customer_accounts:manage', 'idempotent']);
        Route::put('/admin/customers/{id}/status', [CustomerAccountAdminController::class, 'updateStatus'])->middleware(['permission:customer_accounts:manage', 'idempotent']);
        Route::get('/admin/customers/{id}/buyer-profiles', [CustomerAccountAdminController::class, 'buyerProfiles'])->middleware('permission:buyer_profiles:view');
        Route::post('/admin/customers/{customerId}/buyer-profiles/{versionId}/review', [CustomerAccountAdminController::class, 'reviewVersion'])->middleware(['permission:buyer_profiles:review', 'idempotent']);

        // In-App Announcements (Decision W34 / P1-12)
        // User-facing active announcements and interaction states
        Route::get('/announcements/active', [AnnouncementController::class, 'active'])->middleware('permission:announcements:view');
        Route::post('/announcements/{id}/seen', [AnnouncementController::class, 'seen'])->middleware('permission:announcements:view');
        Route::post('/announcements/{id}/acknowledge', [AnnouncementController::class, 'acknowledge'])->middleware('permission:announcements:view');
        Route::post('/announcements/{id}/dismiss', [AnnouncementController::class, 'dismiss'])->middleware('permission:announcements:view');

        // Admin announcements lifecycle and audience management
        Route::get('/admin/announcements', [AnnouncementAdminController::class, 'index'])->middleware('permission:announcements:view');
        Route::post('/admin/announcements', [AnnouncementAdminController::class, 'store'])->middleware(['permission:announcements:draft', 'idempotent']);
        Route::get('/admin/announcements/{id}', [AnnouncementAdminController::class, 'show'])->middleware('permission:announcements:view');
        Route::put('/admin/announcements/{id}/draft', [AnnouncementAdminController::class, 'updateDraft'])->middleware(['permission:announcements:draft', 'idempotent']);
        Route::post('/admin/announcements/{id}/publish', [AnnouncementAdminController::class, 'publish'])->middleware(['permission:announcements:publish', 'idempotent']);
        Route::post('/admin/announcements/{id}/retire', [AnnouncementAdminController::class, 'retire'])->middleware(['permission:announcements:retire', 'idempotent']);
        Route::get('/admin/announcements/{id}/history', [AnnouncementAdminController::class, 'history'])->middleware('permission:announcements:history');

        // Tariffs & Pricing (Decision W29 / P2-01 / P2-10)
        Route::get('/tariffs', [TariffController::class, 'index'])->middleware('permission:tariffs:view');
        Route::get('/tariffs/{id}', [TariffController::class, 'show'])->middleware('permission:tariffs:view');
        Route::get('/vessels', [VesselController::class, 'index'])->middleware('permission:billing:draft');
        Route::get('/admin/tariffs', [AdminTariffController::class, 'index'])->middleware('permission:tariffs:view');
        Route::post('/admin/tariffs', [AdminTariffController::class, 'storeTariff'])->middleware(['permission:tariffs:manage', 'idempotent']);
        Route::put('/admin/tariffs/{id}', [AdminTariffController::class, 'updateTariff'])->middleware(['permission:tariffs:manage', 'idempotent']);
        Route::post('/admin/tariffs/{id}/versions', [AdminTariffController::class, 'storeVersion'])->middleware(['permission:tariffs:manage', 'idempotent']);
        Route::post('/admin/tariffs/{id}/versions/{versionId}/publish', [AdminTariffController::class, 'publishVersion'])->middleware(['permission:tariffs:manage', 'idempotent']);

        // Fuel Price Observations & Surcharge Policies (Decision W29 / P2-10)
        Route::get('/admin/fuel/observations', [FuelSurchargeController::class, 'listObservations'])->middleware('permission:fuel_surcharges:view');
        Route::post('/admin/fuel/observations', [FuelSurchargeController::class, 'storeObservation'])->middleware(['permission:fuel_surcharges:manage', 'idempotent']);
        Route::post('/admin/fuel/observations/{id}/retire', [FuelSurchargeController::class, 'retireObservation'])->middleware(['permission:fuel_surcharges:manage', 'idempotent']);
        Route::get('/admin/fuel/policies', [FuelSurchargeController::class, 'listPolicies'])->middleware('permission:fuel_surcharges:view');
        Route::get('/admin/fuel/policies/{id}', [FuelSurchargeController::class, 'showPolicy'])->middleware('permission:fuel_surcharges:view');
        Route::post('/admin/fuel/policies', [FuelSurchargeController::class, 'storePolicy'])->middleware(['permission:fuel_surcharges:manage', 'idempotent']);
        Route::post('/admin/fuel/policies/{id}/publish', [FuelSurchargeController::class, 'publishPolicy'])->middleware(['permission:fuel_surcharges:manage', 'idempotent']);

        // Billing & Invoice Drafts (Decision W28-W30, W35 / P2-01)
        Route::post('/invoices/calculate', [InvoiceDraftController::class, 'calculate'])->middleware('permission:billing:draft');
        Route::get('/invoices/drafts', [InvoiceDraftController::class, 'index'])->middleware('permission:billing:read');
        Route::post('/invoices/drafts', [InvoiceDraftController::class, 'store'])->middleware(['permission:billing:draft', 'idempotent']);
        Route::get('/invoices/drafts/{id}', [InvoiceDraftController::class, 'show'])->middleware('permission:billing:read');
        Route::put('/invoices/drafts/{id}', [InvoiceDraftController::class, 'update'])->middleware(['permission:billing:draft', 'idempotent']);
        Route::post('/invoices/drafts/{id}/post', [InvoiceDraftController::class, 'post'])->middleware(['permission:billing:post', 'idempotent']);
        Route::get('/invoices/{id}/artifacts', [InvoiceArtifactController::class, 'index']);
        Route::get('/invoices/{id}/artifacts/download', [InvoiceArtifactController::class, 'download']);
        Route::post('/invoices/{id}/artifacts/print', [InvoiceArtifactController::class, 'print']);
        Route::post('/invoices/{id}/artifacts/{artifactId}/retry', [InvoiceArtifactController::class, 'retry'])->middleware('permission:billing:post');

        // Document Series (Decision W07 / P2-02)
        Route::get('/document-series', [DocumentSeriesController::class, 'index'])->middleware('permission:billing:read');
        Route::get('/admin/document-series', [AdminDocumentSeriesController::class, 'index'])->middleware('permission:document_series:manage');
        Route::put('/admin/document-series/{id}/prefix', [AdminDocumentSeriesController::class, 'updatePrefix'])->whereNumber('id')->middleware(['permission:document_series:manage', 'idempotent']);

        // P4-01: periods are explicit master records; a one-use backdate approval is consumed only by issuance.
        Route::get('/admin/accounting-periods', [AccountingPeriodController::class, 'index'])->middleware('permission:periods:view');
        Route::post('/admin/accounting-periods', [AccountingPeriodController::class, 'store'])->middleware(['permission:periods:manage', 'idempotent']);
        Route::post('/admin/accounting-periods/{id}/close', [AccountingPeriodController::class, 'close'])->middleware(['permission:periods:manage', 'idempotent']);
        Route::get('/backdate-authorizations', [BackdateAuthorizationController::class, 'index'])->middleware('permission:backdates:view');
        Route::post('/backdate-authorizations', [BackdateAuthorizationController::class, 'store'])->middleware(['permission:backdates:request', 'idempotent']);
        Route::post('/backdate-authorizations/{id}/approve', [BackdateAuthorizationController::class, 'approve'])->middleware(['permission:backdates:review', 'idempotent']);
        Route::post('/backdate-authorizations/{id}/reject', [BackdateAuthorizationController::class, 'reject'])->middleware(['permission:backdates:review', 'idempotent']);

        // Immutable collection receipt / official-receipt posting (P3-02)
        Route::post('/receipts', [ReceiptController::class, 'post'])->middleware(['permission:receipts:post', 'idempotent']);
        Route::get('/receipts/{id}/artifacts/download', [ReceiptArtifactController::class, 'download']);

        // P4-02 statements are immutable as-of receivable snapshots and never settle invoices.
        Route::get('/account-statements', [AccountStatementController::class, 'index'])->middleware('permission:statements:view');
        Route::get('/account-statements/{id}', [AccountStatementController::class, 'show'])->middleware('permission:statements:view');
        Route::post('/account-statements', [AccountStatementController::class, 'generate'])->middleware(['permission:statements:generate', 'idempotent']);
        Route::get('/account-statements/{id}/artifact/download', [OperationalSnapshotArtifactController::class, 'downloadStatement'])->middleware('permission:statements:view');
        Route::post('/account-statements/{id}/artifact/retry', [OperationalSnapshotArtifactController::class, 'retryStatement'])->middleware(['permission:statements:generate', 'idempotent']);

        // P4-02: operational grouping snapshots. These never post/settle source documents.
        Route::get('/transmittals', [TransmittalController::class, 'index'])->middleware('permission:transmittals:view');
        Route::get('/transmittals/eligible-sources', [TransmittalController::class, 'eligibleSources'])->middleware('permission:transmittals:generate');
        Route::get('/transmittals/{id}', [TransmittalController::class, 'show'])->middleware('permission:transmittals:view');
        Route::post('/transmittals', [TransmittalController::class, 'generate'])->middleware(['permission:transmittals:generate', 'idempotent']);
        Route::get('/transmittals/{id}/artifact/download', [OperationalSnapshotArtifactController::class, 'downloadTransmittal'])->middleware('permission:transmittals:view');
        Route::post('/transmittals/{id}/artifact/retry', [OperationalSnapshotArtifactController::class, 'retryTransmittal'])->middleware(['permission:transmittals:generate', 'idempotent']);

        // P3-09 keeps PPA validation read-only: a result is not a release or a payment action.
        Route::get('/ppa/share-report', [PpaShareReportController::class, 'index'])->middleware('permission:ppa:verify');
        Route::get('/ppa/documents/{number}', [PpaClearanceController::class, 'resolve'])->middleware('permission:ppa:verify');
        Route::get('/ppa/bills/{invoiceNumber}/settlement', [PpaClearanceController::class, 'verify'])->middleware('permission:ppa:verify');
        Route::get('/ppa/bills/{invoiceNumber}/layout', [PpaClearanceController::class, 'layout'])->middleware('permission:ppa:verify');
        Route::get('/ppa/receipts/{receiptNumber}/layout', [PpaClearanceController::class, 'receiptLayout'])->middleware('permission:ppa:verify');
        Route::get('/admin/ppa-clearance-policies', [PpaClearanceController::class, 'policies'])->middleware('permission:ppa_clearance_policies:view');
        Route::post('/admin/ppa-clearance-policies', [PpaClearanceController::class, 'storePolicy'])->middleware(['permission:ppa_clearance_policies:manage', 'idempotent']);
        Route::post('/admin/ppa-clearance-policies/{id}/publish', [PpaClearanceController::class, 'publishPolicy'])->middleware(['permission:ppa_clearance_policies:manage', 'idempotent']);

        // Versioned manual payment routing, instruction and deadline snapshots (P3-10).
        // P3-06 remains the only owner of a real gateway attempt/provider confirmation.
        Route::get('/admin/payment-policies', [PaymentPolicyController::class, 'index'])->middleware('permission:payment_policies:view');
        Route::post('/admin/payment-policies', [PaymentPolicyController::class, 'store'])->middleware(['permission:payment_policies:manage', 'idempotent']);
        Route::post('/admin/payment-policies/{id}/publish', [PaymentPolicyController::class, 'publish'])->middleware(['permission:payment_policies:manage', 'idempotent']);
        Route::get('/portal/payment-groups', [PaymentPolicyController::class, 'portalIndex'])->middleware('permission:proofs:upload');
        Route::post('/portal/payment-groups/manual-instruction', [PaymentPolicyController::class, 'issueManualInstruction'])->middleware(['permission:proofs:upload', 'idempotent']);

        // Customer-selected manual payment proofs and their independent teller-review queue (P3-05)
        Route::get('/portal/bills', [ManualPaymentProofController::class, 'bills'])->middleware('permission:billing:read_own');
        Route::get('/portal/bills/{id}', [ManualPaymentProofController::class, 'billShow'])->middleware('permission:billing:read_own');
        Route::get('/portal/payment-submissions', [ManualPaymentProofController::class, 'index'])->middleware('permission:proofs:upload');
        Route::post('/portal/payment-submissions', [ManualPaymentProofController::class, 'store'])->middleware(['permission:proofs:upload', 'idempotent']);
        Route::post('/portal/payment-submissions/{id}/resubmit', [ManualPaymentProofController::class, 'resubmit'])->middleware(['permission:proofs:upload', 'idempotent']);
        Route::get('/teller/payment-submissions', [ManualPaymentProofController::class, 'tellerIndex'])->middleware('permission:proofs:review');
        Route::post('/teller/payment-submissions/claim-next', [ManualPaymentProofController::class, 'claimNext'])->middleware(['permission:proofs:review', 'idempotent']);
        Route::get('/teller/payment-submissions/{id}', [ManualPaymentProofController::class, 'tellerShow'])->middleware('permission:proofs:review');
        Route::post('/teller/payment-submissions/{id}/approve', [ManualPaymentProofController::class, 'approve'])->middleware(['permission:proofs:review', 'permission:receipts:post', 'idempotent']);
        Route::post('/teller/payment-submissions/{id}/reject', [ManualPaymentProofController::class, 'reject'])->middleware(['permission:proofs:review', 'idempotent']);
        Route::post('/teller/payment-submissions/{id}/check-clearance', [ManualPaymentProofController::class, 'recordCheckClearance'])->middleware(['permission:proofs:review', 'permission:checks:confirm_clearance', 'idempotent']);

        // VIP credit policy, immutable credit assignments, and bank-transfer repayment (P3-08).
        // A credit charge is not a second receivable or a receipt; only approved repayment posts through ReceiptPostingService.
        Route::get('/admin/credit-policies', [VipCreditController::class, 'policies'])->middleware('permission:credit_policies:view');
        Route::post('/admin/credit-policies', [VipCreditController::class, 'createPolicy'])->middleware(['permission:credit_policies:manage', 'idempotent']);
        Route::post('/admin/credit-policies/{id}/publish', [VipCreditController::class, 'publishPolicy'])->middleware(['permission:credit_policies:manage', 'idempotent']);
        Route::delete('/admin/credit-policies/{id}', [VipCreditController::class, 'deletePolicy'])->middleware(['permission:credit_policies:manage', 'idempotent']);
        Route::get('/admin/late-charge-policies', [LateChargeController::class, 'policies'])->middleware('permission:credit_late_charge_policies:view');
        Route::post('/admin/late-charge-policies', [LateChargeController::class, 'createPolicy'])->middleware(['permission:credit_late_charge_policies:manage', 'idempotent']);
        Route::post('/admin/late-charge-policies/{id}/publish', [LateChargeController::class, 'publishPolicy'])->middleware(['permission:credit_late_charge_policies:manage', 'idempotent']);
        Route::delete('/admin/late-charge-policies/{id}', [LateChargeController::class, 'deletePolicy'])->middleware(['permission:credit_late_charge_policies:manage', 'idempotent']);
        Route::get('/admin/late-charge-assessments', [LateChargeController::class, 'assessments'])->middleware('permission:credit_late_charges:view');
        Route::post('/admin/late-charge-assessments/run', [LateChargeController::class, 'runAssessments'])->middleware(['permission:credit_late_charges:post', 'idempotent']);
        Route::post('/admin/late-charge-assessments/{id}/waive', [LateChargeController::class, 'waive'])->middleware(['permission:credit_late_charges:waive', 'idempotent']);
        Route::get('/admin/credit-accounts', [VipCreditController::class, 'accounts'])->middleware('permission:credit_accounts:view');
        Route::post('/admin/credit-accounts/{customerId}/versions', [VipCreditController::class, 'configureAccount'])->middleware(['permission:credit_accounts:manage', 'idempotent']);
        Route::get('/credit-accounts/aging', [VipCreditAgingController::class, 'staffIndex'])->middleware('permission:credit_aging:view');
        Route::get('/credit-accounts/{id}/aging', [VipCreditAgingController::class, 'staffShow'])->middleware('permission:credit_aging:view');
        Route::get('/credit-accounts/{id}', [VipCreditController::class, 'staffSummary'])->middleware('permission:credit_accounts:view');
        Route::get('/portal/credit-account', [VipCreditController::class, 'portalSummary'])->middleware('permission:portal.credit.view');
        Route::get('/portal/credit-aging', [VipCreditAgingController::class, 'portal'])->middleware('permission:portal.credit.view');
        Route::post('/portal/credit-charges', [VipCreditController::class, 'charge'])->middleware(['permission:portal.credit.charge', 'idempotent']);
        Route::get('/portal/credit-repayments', [VipCreditController::class, 'portalRepayments'])->middleware('permission:portal.credit.view');
        Route::post('/portal/credit-repayments', [VipCreditController::class, 'submitRepayment'])->middleware(['permission:portal.credit.view', 'permission:proofs:upload', 'idempotent']);
        Route::post('/portal/credit-repayments/{id}/resubmit', [VipCreditController::class, 'resubmitRepayment'])->middleware(['permission:portal.credit.view', 'permission:proofs:upload', 'idempotent']);
        Route::get('/teller/credit-repayments', [VipCreditController::class, 'tellerRepayments'])->middleware('permission:credit:review_proof');
        Route::post('/teller/credit-repayments/claim-next', [VipCreditController::class, 'claimNextRepayment'])->middleware(['permission:credit:review_proof', 'idempotent']);
        Route::post('/teller/credit-repayments/{id}/approve', [VipCreditController::class, 'approveRepayment'])->middleware(['permission:credit:review_proof', 'permission:receipts:post', 'idempotent']);
        Route::post('/teller/credit-repayments/{id}/reject', [VipCreditController::class, 'rejectRepayment'])->middleware(['permission:credit:review_proof', 'idempotent']);

        // Admin Document Studio (Decision W28 / P2-03)
        Route::get('/admin/document-studio/templates', [DocumentStudioController::class, 'index'])->middleware('permission:templates:view');
        Route::post('/admin/document-studio/templates', [DocumentStudioController::class, 'store'])->middleware(['permission:templates:draft', 'idempotent']);
        Route::get('/admin/document-studio/templates/{id}', [DocumentStudioController::class, 'show'])->middleware('permission:templates:view');
        Route::post('/admin/document-studio/templates/{id}/versions', [DocumentStudioController::class, 'storeVersion'])->middleware(['permission:templates:draft', 'idempotent']);
        Route::get('/admin/document-studio/templates/{id}/versions/{versionId}', [DocumentStudioController::class, 'showVersion'])->middleware('permission:templates:view');
        Route::put('/admin/document-studio/templates/{id}/versions/{versionId}', [DocumentStudioController::class, 'updateVersion'])->middleware(['permission:templates:draft', 'idempotent']);
        Route::post('/admin/document-studio/templates/{id}/versions/{versionId}/validate', [DocumentStudioController::class, 'validateVersion'])->middleware('permission:templates:validate');
        Route::post('/admin/document-studio/templates/{id}/versions/{versionId}/preview', [DocumentStudioController::class, 'previewVersion'])->middleware('permission:templates:preview');
        Route::post('/admin/document-studio/templates/{id}/versions/{versionId}/publish', [DocumentStudioController::class, 'publishVersion'])->middleware(['permission:templates:publish', 'idempotent']);
        Route::post('/admin/document-studio/templates/{id}/versions/{versionId}/retire', [DocumentStudioController::class, 'retireVersion'])->middleware(['permission:templates:retire', 'idempotent']);
        Route::get('/admin/document-studio/activations', [DocumentStudioController::class, 'activations'])->middleware('permission:templates:view');
        Route::post('/admin/document-studio/activations', [DocumentStudioController::class, 'activate'])->middleware(['permission:templates:activate', 'idempotent']);
        Route::get('/admin/document-studio/assets', [DocumentTemplateAssetController::class, 'index'])->middleware('permission:templates:view');
        Route::post('/admin/document-studio/assets', [DocumentTemplateAssetController::class, 'store'])->middleware(['permission:templates:assets:manage', 'idempotent']);
        Route::get('/admin/document-studio/assets/{id}/download', [DocumentTemplateAssetController::class, 'download'])->middleware('permission:templates:view');
        Route::post('/admin/document-studio/assets/{id}/retire', [DocumentTemplateAssetController::class, 'retire'])->middleware(['permission:templates:assets:manage', 'idempotent']);

        // Fiscal Invoice & Structured Data Contract (Decision BIR-07 / P2-06)
        Route::get('/invoices/{id}/fiscal-data', [FiscalInvoiceController::class, 'showFiscalData']);
        Route::get('/fiscal/taxpayer-profile', [FiscalInvoiceController::class, 'showTaxpayerProfile'])->middleware('permission:billing:read');
        Route::get('/fiscal/tax-rules', [FiscalInvoiceController::class, 'listTaxRules'])->middleware('permission:billing:read');

        // Customer Billing Requests (Decision W21, W22 / P2-07)
        Route::get('/customer/billing-requests', [CustomerBillingRequestController::class, 'index']);
        Route::post('/customer/billing-requests', [CustomerBillingRequestController::class, 'store'])->middleware('idempotent');
        Route::get('/customer/billing-requests/{id}', [CustomerBillingRequestController::class, 'show']);
        Route::post('/customer/billing-requests/{id}/documents', [CustomerBillingRequestController::class, 'attachDocument'])->middleware('idempotent');
        Route::delete('/customer/billing-requests/{id}/documents/{documentId}', [CustomerBillingRequestController::class, 'removeDocument'])->middleware('idempotent');
        Route::post('/customer/billing-requests/{id}/submit', [CustomerBillingRequestController::class, 'submit'])->middleware('idempotent');
        Route::post('/customer/billing-requests/{id}/resubmit', [CustomerBillingRequestController::class, 'resubmit'])->middleware('idempotent');
        Route::post('/customer/billing-requests/{id}/cancel', [CustomerBillingRequestController::class, 'cancel'])->middleware('idempotent');

        // Teller Billing Request Queue (Decision W22 / P2-07)
        Route::get('/teller/queue', [TellerQueueController::class, 'queueSummary'])->middleware('permission:billing:read');
        Route::post('/teller/queue/claim-next', [TellerQueueController::class, 'claimNext'])->middleware(['permission:billing:draft', 'idempotent']);
        Route::get('/teller/billing-requests/{id}', [TellerQueueController::class, 'show'])->middleware('permission:billing:read');
        Route::post('/teller/billing-requests/{id}/heartbeat', [TellerQueueController::class, 'heartbeat'])->middleware('permission:billing:draft');
        Route::post('/teller/billing-requests/{id}/request-correction', [TellerQueueController::class, 'requestCorrection'])->middleware(['permission:billing:draft', 'idempotent']);
        Route::post('/teller/billing-requests/{id}/prepare-draft', [TellerQueueController::class, 'prepareDraft'])->middleware(['permission:billing:draft', 'idempotent']);
        Route::post('/teller/billing-requests/{id}/mark-bill-ready', [TellerQueueController::class, 'markBillReady'])->middleware(['permission:billing:draft', 'idempotent']);
        Route::post('/teller/billing-requests/{id}/release', [TellerQueueController::class, 'release'])->middleware(['permission:billing:draft', 'idempotent']);
        Route::post('/teller/billing-requests/{id}/cancel', [TellerQueueController::class, 'cancel'])->middleware(['permission:billing:draft', 'idempotent']);
        Route::post('/teller/queue/recover-stale', [TellerQueueController::class, 'recoverStale'])->middleware('permission:billing:draft');

        // Customer Tax Evidence (Decision W20 / P2-08)
        Route::get('/customer/tax-evidence/withholding', [CustomerTaxEvidenceController::class, 'listWithholding']);
        Route::post('/customer/tax-evidence/withholding', [CustomerTaxEvidenceController::class, 'storeWithholding'])->middleware('idempotent');
        Route::get('/customer/tax-evidence/exemptions', [CustomerTaxEvidenceController::class, 'listExemptions']);
        Route::post('/customer/tax-evidence/exemptions', [CustomerTaxEvidenceController::class, 'storeExemption'])->middleware('idempotent');

        // Admin Tax Evidence Review (Decision W20 / P2-08)
        Route::get('/admin/tax-evidence/withholding', [AdminTaxEvidenceController::class, 'listWithholding'])->middleware('permission:billing:read');
        Route::post('/admin/tax-evidence/withholding/{id}/review', [AdminTaxEvidenceController::class, 'reviewWithholding'])->middleware(['permission:billing:draft', 'idempotent']);
        Route::post('/admin/tax-evidence/withholding/{id}/revoke', [AdminTaxEvidenceController::class, 'revokeWithholding'])->middleware(['permission:billing:draft', 'idempotent']);
        Route::get('/admin/tax-evidence/exemptions', [AdminTaxEvidenceController::class, 'listExemptions'])->middleware('permission:billing:read');
        Route::post('/admin/tax-evidence/exemptions/{id}/review', [AdminTaxEvidenceController::class, 'reviewExemption'])->middleware(['permission:billing:draft', 'idempotent']);
        Route::post('/admin/tax-evidence/exemptions/{id}/revoke', [AdminTaxEvidenceController::class, 'revokeExemption'])->middleware(['permission:billing:draft', 'idempotent']);

        // Walk-in Billing (Decision W25 / P2-09)
        Route::get('/teller/walk-in/customers', [WalkInBillingController::class, 'index'])->middleware('permission:billing:read');
        Route::post('/teller/walk-in/customers', [WalkInBillingController::class, 'store'])->middleware(['permission:billing:draft', 'idempotent']);
        Route::get('/teller/walk-in/customers/{id}', [WalkInBillingController::class, 'show'])->middleware('permission:billing:read');
        Route::put('/teller/walk-in/customers/{id}', [WalkInBillingController::class, 'update'])->middleware(['permission:billing:draft', 'idempotent']);
        Route::patch('/teller/walk-in/customers/{id}', [WalkInBillingController::class, 'update'])->middleware(['permission:billing:draft', 'idempotent']);
        Route::post('/teller/walk-in/customers/{id}/invoice-draft', [WalkInBillingController::class, 'createInvoiceDraft'])->middleware(['permission:billing:draft', 'idempotent']);
        Route::post('/teller/walk-in/customers/{id}/link', [WalkInBillingController::class, 'linkToPortalCustomer'])->middleware(['permission:bill_claims:review', 'idempotent']);

        // Portal Bill Claims (Decision W25 / P2-09)
        Route::get('/portal/bill-claims', [BillClaimController::class, 'index']);
        Route::post('/portal/bill-claims', [BillClaimController::class, 'store'])->middleware('idempotent');
        Route::post('/portal/bill-claims/{id}/verify', [BillClaimController::class, 'verify'])->middleware('idempotent');
        Route::get('/portal/bill-claims/{id}/preview', [BillClaimController::class, 'preview']);
        Route::post('/portal/bill-claims/{id}/accept', [BillClaimController::class, 'accept'])->middleware('idempotent');
        Route::post('/portal/bill-claims/{id}/decline', [BillClaimController::class, 'decline'])->middleware('idempotent');
        Route::post('/portal/bill-claims/{id}/cancel', [BillClaimController::class, 'cancel'])->middleware('idempotent');

        // Teller/Admin Bill Claim Review (Decision W25 / P2-09)
        Route::get('/teller/bill-claims', [TellerClaimReviewController::class, 'index'])->middleware('permission:bill_claims:review');
        Route::get('/teller/bill-claims/{id}', [TellerClaimReviewController::class, 'show'])->middleware('permission:bill_claims:review');
        Route::post('/teller/bill-claims/{id}/decide', [TellerClaimReviewController::class, 'decide'])->middleware(['permission:bill_claims:review', 'idempotent']);
    });
});
