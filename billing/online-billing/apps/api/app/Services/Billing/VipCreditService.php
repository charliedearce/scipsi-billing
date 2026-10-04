<?php

namespace App\Services\Billing;

use App\Exceptions\ConcurrencyException;
use App\Models\AuditEvent;
use App\Models\CreditAccountEvent;
use App\Models\CreditPolicyVersion;
use App\Models\Customer;
use App\Models\CustomerCreditAccount;
use App\Models\CustomerCreditAccountVersion;
use App\Models\CustomerUserLink;
use App\Models\Invoice;
use App\Models\InvoiceCreditCharge;
use App\Models\ManualPaymentSubmissionItem;
use App\Models\ManualPaymentSubmissionProof;
use App\Models\PrivateFile;
use App\Models\PrivateFileVersion;
use App\Models\Receipt;
use App\Models\ReceiptAllocation;
use App\Models\User;
use App\Models\VipCreditRepaymentAllocation;
use App\Models\VipCreditRepaymentProof;
use App\Models\VipCreditRepaymentSubmission;
use App\Services\Notifications\InAppNotificationPublisher;
use App\Services\Sms\NotificationEventRecorder;
use App\Services\Sms\SmsDeliveryOrchestrator;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Owns VIP credit eligibility, immutable term assignment and bank-transfer repayment.
 * Invoice balances remain authoritative in receipts/allocations; this service never creates a
 * second receivable or a receipt when a bill is merely charged to credit.
 */
class VipCreditService
{
    public function __construct(
        protected ReceiptPostingService $receiptPostingService,
        protected BankTransferSettlementReferenceService $bankTransferReferences,
        protected SmsDeliveryOrchestrator $smsOrchestrator,
        protected NotificationEventRecorder $notificationEvents,
        protected LateChargePolicyService $lateChargePolicies,
        protected InAppNotificationPublisher $notifications,
    ) {}

    /** @return Collection<int, CreditPolicyVersion> */
    public function listPolicies(User $actor): Collection
    {
        return CreditPolicyVersion::where('organization_id', $actor->organization_id)
            ->with(['createdBy:id,name,email', 'publishedBy:id,name,email'])
            ->orderByDesc('version_number')->get();
    }

    /** @param array<string,mixed> $data */
    public function createPolicyDraft(User $actor, array $data): CreditPolicyVersion
    {
        return DB::transaction(function () use ($actor, $data): CreditPolicyVersion {
            $latest = CreditPolicyVersion::where('organization_id', $actor->organization_id)
                ->orderByDesc('version_number')->lockForUpdate()->first();
            $next = ((int) ($latest?->version_number ?? 0)) + 1;
            $this->assertPolicyShape($data);
            $policy = CreditPolicyVersion::create([
                'organization_id' => $actor->organization_id,
                'version_number' => $next,
                'currency' => strtoupper((string) ($data['currency'] ?? 'PHP')),
                'default_credit_limit_mode' => strtoupper((string) $data['default_credit_limit_mode']),
                'default_credit_limit_amount' => $this->nullableDecimal($data['default_credit_limit_amount'] ?? null),
                'payment_terms_days' => (int) $data['payment_terms_days'],
                'due_date_basis' => strtoupper((string) $data['due_date_basis']),
                'overdue_restriction' => strtoupper((string) $data['overdue_restriction']),
                'overdue_grace_days' => (int) ($data['overdue_grace_days'] ?? 0),
                'overdue_amount_threshold' => $this->nullableDecimal($data['overdue_amount_threshold'] ?? null),
                'allow_customer_overrides' => (bool) ($data['allow_customer_overrides'] ?? false),
                'status' => CreditPolicyVersion::STATUS_DRAFT,
                'effective_from' => Carbon::parse($data['effective_from'], 'Asia/Manila'),
                'effective_to' => isset($data['effective_to']) ? Carbon::parse($data['effective_to'], 'Asia/Manila') : null,
                'created_by_user_id' => $actor->id,
                'lock_version' => 1,
            ]);
            $this->auditPolicy($policy, $actor, 'CREDIT_POLICY_DRAFT_CREATED', 'credit_policies:manage', 'Credit policy draft created.');

            return $policy->fresh(['createdBy:id,name,email']);
        });
    }

    public function deletePolicyDraft(CreditPolicyVersion $policy, User $actor, int $expectedVersion): void
    {
        DB::transaction(function () use ($policy, $actor, $expectedVersion): void {
            $locked = CreditPolicyVersion::where('organization_id', $actor->organization_id)
                ->whereKey($policy->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($locked->status !== CreditPolicyVersion::STATUS_DRAFT) {
                throw ValidationException::withMessages(['policy' => ['Only a draft credit policy can be deleted.']]);
            }
            if ($locked->lock_version !== $expectedVersion) {
                throw new ConcurrencyException('Credit policy changed since it was loaded. Refresh before deleting.');
            }
            if (InvoiceCreditCharge::where('credit_policy_version_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['policy' => ['This credit policy is referenced by a credit charge and cannot be deleted.']]);
            }

            $this->auditPolicy($locked, $actor, 'CREDIT_POLICY_DRAFT_DELETED', 'credit_policies:manage', 'Credit policy draft deleted.');
            $locked->delete();
        });
    }

    public function publishPolicy(CreditPolicyVersion $policy, User $actor, int $expectedVersion, string $reason): CreditPolicyVersion
    {
        return DB::transaction(function () use ($policy, $actor, $expectedVersion, $reason): CreditPolicyVersion {
            $locked = CreditPolicyVersion::where('organization_id', $actor->organization_id)->whereKey($policy->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== CreditPolicyVersion::STATUS_DRAFT) {
                throw ValidationException::withMessages(['policy' => ['Only a draft credit policy can be published.']]);
            }
            if ($locked->lock_version !== $expectedVersion) {
                throw new ConcurrencyException('Credit policy changed since it was loaded. Refresh before publishing.');
            }
            // Supersede open-ended published windows. Drafts created with an earlier
            // Effective from than the current published version are advanced so Admin
            // is not blocked by overlap (the form has no end-date action).
            $this->preparePublishedCreditPolicyWindow($locked);
            $this->assertNoPublishedOverlap($locked);
            $now = Carbon::now('Asia/Manila');
            $locked->refresh();
            $locked->update([
                'status' => CreditPolicyVersion::STATUS_PUBLISHED,
                'published_by_user_id' => $actor->id,
                'published_at' => $now,
                'publication_reason' => $reason,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->auditPolicy($locked, $actor, 'CREDIT_POLICY_PUBLISHED', 'credit_policies:manage', $reason);

            return $locked->fresh(['createdBy:id,name,email', 'publishedBy:id,name,email']);
        });
    }

    /** @param array<string,mixed> $data */
    public function configureAccount(User $actor, int $customerId, array $data): CustomerCreditAccount
    {
        return DB::transaction(function () use ($actor, $customerId, $data): CustomerCreditAccount {
            $customer = Customer::where('organization_id', $actor->organization_id)->whereKey($customerId)->lockForUpdate()->firstOrFail();
            $this->assertVipCustomer($customer);
            $effectiveAt = Carbon::parse($data['effective_from'], 'Asia/Manila');
            $policy = $this->effectivePolicy($actor->organization_id, $effectiveAt, true);
            if (! $policy) {
                throw ValidationException::withMessages(['effective_from' => ['A published organization credit policy must be effective before a VIP account profile can be configured.']]);
            }
            $overrideFields = $this->overrideFields($data);
            if ($overrideFields !== [] && ! $policy->allow_customer_overrides) {
                throw ValidationException::withMessages(['overrides' => ['The effective organization credit policy does not permit customer-specific overrides.']]);
            }
            $this->assertOverrideShape($data);

            $account = CustomerCreditAccount::where('organization_id', $actor->organization_id)
                ->where('customer_id', $customer->id)->lockForUpdate()->first();
            if (! $account) {
                $account = CustomerCreditAccount::create([
                    'organization_id' => $actor->organization_id,
                    'customer_id' => $customer->id,
                    'created_by_user_id' => $actor->id,
                    'lock_version' => 1,
                ]);
                $account = CustomerCreditAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            } elseif (isset($data['expected_account_lock_version']) && $account->lock_version !== (int) $data['expected_account_lock_version']) {
                throw new ConcurrencyException('The credit account changed since it was loaded. Refresh before saving.');
            }

            $latestVersion = CustomerCreditAccountVersion::where('customer_credit_account_id', $account->id)
                ->orderByDesc('version_number')->lockForUpdate()->first();
            $version = ((int) ($latestVersion?->version_number ?? 0)) + 1;
            $profile = CustomerCreditAccountVersion::create([
                'customer_credit_account_id' => $account->id,
                'version_number' => $version,
                'status' => strtoupper((string) $data['status']),
                'credit_limit_mode_override' => $this->nullableUpper($data['credit_limit_mode_override'] ?? null),
                'credit_limit_amount_override' => $this->nullableDecimal($data['credit_limit_amount_override'] ?? null),
                'payment_terms_days_override' => $data['payment_terms_days_override'] ?? null,
                'due_date_basis_override' => $this->nullableUpper($data['due_date_basis_override'] ?? null),
                'overdue_restriction_override' => $this->nullableUpper($data['overdue_restriction_override'] ?? null),
                'overdue_grace_days_override' => $data['overdue_grace_days_override'] ?? null,
                'overdue_amount_threshold_override' => $this->nullableDecimal($data['overdue_amount_threshold_override'] ?? null),
                'effective_from' => $effectiveAt,
                'effective_to' => isset($data['effective_to']) ? Carbon::parse($data['effective_to'], 'Asia/Manila') : null,
                'created_by_user_id' => $actor->id,
                'reason' => $data['reason'],
                'lock_version' => 1,
            ]);
            $account->update(['lock_version' => $account->lock_version + 1]);
            $this->accountEvent($account, $actor, 'PROFILE_VERSION_PUBLISHED', $data['reason'], [
                'account_version_id' => $profile->id,
                'account_version_number' => $profile->version_number,
                'status' => $profile->status,
                'effective_from' => $profile->effective_from->toIso8601String(),
            ]);
            $this->auditAccount($account, $actor, 'CREDIT_ACCOUNT_PROFILE_PUBLISHED', 'credit_accounts:manage', $data['reason']);

            // A future-dated profile is not action-affecting yet, so it must not announce a hold early.
            $now = Carbon::now('Asia/Manila');
            if (in_array($profile->status, [CustomerCreditAccountVersion::STATUS_HELD, CustomerCreditAccountVersion::STATUS_DISABLED], true)
                && $profile->effective_from->lessThanOrEqualTo($now)
                && ($profile->effective_to === null || $profile->effective_to->greaterThan($now))) {
                $recipient = $this->primaryPortalRecipient($customer);
                if ($recipient) {
                    $this->dispatchVipTransactionalNotice(
                        $account->organization_id,
                        'VIP_CREDIT_ACCOUNT_HELD',
                        'customer_credit_account_version',
                        $profile->id,
                        $recipient,
                        [
                            'recipient_name' => $recipient->name,
                            'reference_no' => $customer->account_number,
                            'action_label' => 'VIP credit account review required',
                            'date_formatted' => $profile->effective_from->timezone('Asia/Manila')->format('M d, Y'),
                            'org_name' => 'SCIPSI',
                        ],
                    );
                }
            }

            return $this->loadAccount($account);
        });
    }

    /** @return array<string,mixed> */
    public function portalSummary(User $actor, int $customerId): array
    {
        $customer = $this->assertPortalAccess($actor, $customerId);
        $account = CustomerCreditAccount::where('organization_id', $actor->organization_id)->where('customer_id', $customer->id)->first();
        if (! $account) {
            return [
                'eligible' => false,
                'reason' => 'No VIP credit account has been configured for this customer. An Administrator must open Customer Accounts, select this VIP client, and publish a VIP Credit profile (status ACTIVE) after a published organization credit policy is in effect.',
            ];
        }

        return $this->summary($account, Carbon::now('Asia/Manila'));
    }

    /** @return array<string,mixed> */
    public function staffSummary(User $actor, CustomerCreditAccount $account): array
    {
        if ($account->organization_id !== $actor->organization_id) {
            throw new AuthorizationException('Credit account is outside your organization scope.');
        }

        return $this->summary($account, Carbon::now('Asia/Manila'));
    }

    /** @param array{customer_id:int,allocations:array<int,array{invoice_id:int,expected_invoice_lock_version:int,requested_amount:string}>} $data */
    public function charge(User $actor, array $data): array
    {
        return DB::transaction(function () use ($actor, $data): array {
            $customer = $this->assertPortalAccess($actor, (int) $data['customer_id'], true);
            $this->assertVipCustomer($customer);
            $account = CustomerCreditAccount::where('organization_id', $actor->organization_id)
                ->where('customer_id', $customer->id)->lockForUpdate()->first();
            if (! $account) {
                throw ValidationException::withMessages(['customer_id' => ['No VIP credit account has been configured.']]);
            }
            $now = Carbon::now('Asia/Manila');
            $policy = $this->effectivePolicy($actor->organization_id, $now, true);
            if (! $policy) {
                throw ValidationException::withMessages(['credit_policy' => ['No published credit policy is effective for this organization.']]);
            }
            $profile = $this->effectiveAccountVersion($account->id, $now, true);
            if (! $profile) {
                throw ValidationException::withMessages(['credit_account' => ['No active credit-account profile is effective for this customer.']]);
            }
            $terms = $this->resolveTerms($policy, $profile);
            if ($profile->status !== CustomerCreditAccountVersion::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['credit_account' => ['This VIP credit account is not active for new charges. Existing debt remains repayable.']]);
            }
            $this->assertNoOverdueBlock($account, $terms, $now);

            $inputs = collect($data['allocations'])->keyBy(fn (array $input) => (int) $input['invoice_id']);
            if ($inputs->count() !== count($data['allocations'])) {
                throw ValidationException::withMessages(['allocations' => ['Each bill can be charged to credit only once in a request.']]);
            }
            $invoiceIds = $inputs->keys()->sort()->values()->all();
            $invoices = Invoice::where('organization_id', $actor->organization_id)->whereIn('id', $invoiceIds)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($invoices->count() !== count($invoiceIds)) {
                throw ValidationException::withMessages(['allocations' => ['One or more selected bills are unavailable.']]);
            }
            $existingCharges = InvoiceCreditCharge::whereIn('invoice_id', $invoiceIds)->lockForUpdate()->pluck('invoice_id')->all();
            if ($existingCharges !== []) {
                throw ValidationException::withMessages(['allocations' => ['One or more selected bills are already charged to credit and retain their original terms.']]);
            }
            $this->assertNoPendingProofSelection($invoiceIds);

            $selectedTotal = '0.00';
            foreach ($invoiceIds as $invoiceId) {
                $invoice = $invoices->get($invoiceId);
                $input = $inputs->get($invoiceId);
                if ($invoice->status !== 'POSTED' || $invoice->customer_id !== $customer->id || strtoupper($invoice->currency) !== strtoupper($policy->currency)) {
                    throw ValidationException::withMessages(['allocations' => ["Invoice {$invoiceId} is not an eligible posted bill for this VIP account and policy currency."]]);
                }
                if ($invoice->lock_version !== (int) $input['expected_invoice_lock_version']) {
                    throw new ConcurrencyException("Invoice {$invoiceId} changed. Refresh balances before charging to credit.");
                }
                $outstanding = $this->invoiceOutstanding($invoice->id, (string) $invoice->total_charge_amount);
                // Split cash/credit and re-aging a partially paid invoice are deliberately not part of P3-08.
                if (bccomp($outstanding, (string) $invoice->total_charge_amount, 2) !== 0 || bccomp($this->decimal((string) $input['requested_amount']), $outstanding, 2) !== 0) {
                    throw ValidationException::withMessages(['allocations' => ["Invoice {$invoiceId} must be fully unpaid and selected at its full balance of {$outstanding} for a VIP credit charge."]]);
                }
                $selectedTotal = bcadd($selectedTotal, $outstanding, 2);
            }
            $exposure = $this->creditExposure($account->id, $policy->currency, true);
            if ($terms['credit_limit_mode'] === 'CAPPED' && bccomp(bcadd($exposure, $selectedTotal, 2), $terms['credit_limit_amount'], 2) > 0) {
                throw ValidationException::withMessages(['allocations' => ['Selected bills exceed current available VIP credit of '.$this->availableCredit($terms, $exposure).'.']]);
            }

            $charges = [];
            $lateChargePolicy = $this->lateChargePolicies->effectivePolicy($actor->organization_id, $policy->currency, $now, true);
            $lateChargeSnapshot = $lateChargePolicy ? $this->lateChargePolicies->snapshot($lateChargePolicy) : null;
            foreach ($invoiceIds as $invoiceId) {
                $invoice = $invoices->get($invoiceId);
                $baseDate = $terms['due_date_basis'] === 'INVOICE_DATE' ? $invoice->business_date->copy() : $now->copy()->startOfDay();
                $dueDate = $baseDate->addDays($terms['payment_terms_days'])->toDateString();
                $charge = InvoiceCreditCharge::create([
                    'organization_id' => $actor->organization_id,
                    'customer_credit_account_id' => $account->id,
                    'customer_id' => $customer->id,
                    'invoice_id' => $invoice->id,
                    'credit_policy_version_id' => $policy->id,
                    'credit_policy_version_number' => $policy->version_number,
                    'credit_account_version_id' => $profile->id,
                    'credit_account_version_number' => $profile->version_number,
                    'late_charge_policy_version_id' => $lateChargePolicy?->id,
                    'late_charge_policy_version_number' => $lateChargePolicy?->version_number,
                    'late_charge_policy_snapshot' => $lateChargeSnapshot,
                    'currency' => $policy->currency,
                    'charged_amount' => $invoice->total_charge_amount,
                    'payment_terms_days_snapshot' => $terms['payment_terms_days'],
                    'due_date_basis_snapshot' => $terms['due_date_basis'],
                    'due_date' => $dueDate,
                    'terms_snapshot' => $terms,
                    'charged_at' => $now,
                    'charged_business_date' => $now->toDateString(),
                    'charged_by_user_id' => $actor->id,
                ]);
                $this->accountEvent($account, $actor, 'INVOICE_CHARGED_TO_CREDIT', null, [
                    'invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number,
                    'charged_amount' => (string) $charge->charged_amount, 'due_date' => $dueDate,
                    'charged_business_date' => $charge->charged_business_date?->toDateString(),
                    'policy_version' => $policy->version_number, 'account_version' => $profile->version_number,
                    'late_charge_policy_version' => $lateChargePolicy?->version_number,
                ], $charge);
                $charges[] = $charge;
            }
            $this->auditAccount($account, $actor, 'VIP_CREDIT_CHARGE_ASSIGNED', 'portal.credit.charge', 'Customer charged posted invoices to configured VIP credit terms.');
            $this->notifications->publish(
                (int) $actor->organization_id,
                (int) $actor->id,
                'CREDIT',
                'VIP Credit Terms Assigned',
                'Your selected bill(s) are on credit. Review their individual due dates in My Credit.',
                ['customer_credit_account_id' => $account->id, 'invoice_credit_charge_ids' => collect($charges)->pluck('id')->all()],
            );
            foreach ($charges as $charge) {
                $invoice = $invoices->get($charge->invoice_id);
                $this->dispatchVipTransactionalNotice(
                    $account->organization_id,
                    'VIP_CREDIT_ASSIGNED',
                    'invoice_credit_charge',
                    $charge->id,
                    $actor,
                    [
                        'recipient_name' => $actor->name,
                        'reference_no' => $invoice->invoice_number,
                        'action_label' => 'VIP credit terms assigned',
                        'date_formatted' => $charge->due_date->format('M d, Y'),
                        'org_name' => 'SCIPSI',
                    ],
                    $invoice->location_id === null ? [] : [(int) $invoice->location_id],
                );
            }

            return $this->summary($account, $now);
        });
    }

    /** @param array{customer_id:int,proof_file_id:int,declared_reference?:?string,allocations:array<int,array{invoice_id:int,expected_invoice_lock_version:int,requested_amount:string}>} $data */
    public function submitRepayment(User $actor, array $data): VipCreditRepaymentSubmission
    {
        return DB::transaction(function () use ($actor, $data): VipCreditRepaymentSubmission {
            $customer = $this->assertPortalAccess($actor, (int) $data['customer_id'], true);
            $this->assertVipCustomer($customer);
            [$file, $fileVersion] = $this->lockUsableOwnedProof($actor, (int) $data['proof_file_id']);
            $account = CustomerCreditAccount::where('organization_id', $actor->organization_id)
                ->where('customer_id', $customer->id)->lockForUpdate()->first();
            if (! $account) {
                throw ValidationException::withMessages(['customer_id' => ['No VIP credit account is available for repayment.']]);
            }
            if ($this->proofVersionIsUsed($file->id, $fileVersion->version_number)) {
                throw ValidationException::withMessages(['proof_file_id' => ['This exact proof-file version is already linked to a settlement submission.']]);
            }
            $inputs = collect($data['allocations'])->keyBy(fn (array $input) => (int) $input['invoice_id']);
            if ($inputs->count() !== count($data['allocations'])) {
                throw ValidationException::withMessages(['allocations' => ['Each credit bill may appear only once in a repayment.']]);
            }
            $invoiceIds = $inputs->keys()->sort()->values()->all();
            $charges = InvoiceCreditCharge::where('customer_credit_account_id', $account->id)->whereIn('invoice_id', $invoiceIds)
                ->orderBy('invoice_id')->lockForUpdate()->get()->keyBy('invoice_id');
            if ($charges->count() !== count($invoiceIds)) {
                throw ValidationException::withMessages(['allocations' => ['Every repayment allocation must reference an existing credit-charged bill from this account.']]);
            }
            $this->assertNoPendingProofSelection($invoiceIds);
            $invoices = Invoice::where('organization_id', $actor->organization_id)->whereIn('id', $invoiceIds)
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $requestedTotal = '0.00';
            foreach ($invoiceIds as $invoiceId) {
                $invoice = $invoices->get($invoiceId);
                $charge = $charges->get($invoiceId);
                $input = $inputs->get($invoiceId);
                if (! $invoice || $invoice->status !== 'POSTED' || $invoice->customer_id !== $customer->id || strtoupper($invoice->currency) !== strtoupper($charge->currency)) {
                    throw ValidationException::withMessages(['allocations' => ["Invoice {$invoiceId} is not payable by this VIP account."]]);
                }
                if ($invoice->lock_version !== (int) $input['expected_invoice_lock_version']) {
                    throw new ConcurrencyException("Invoice {$invoiceId} changed. Refresh your credit account before submitting repayment.");
                }
                $amount = $this->decimal((string) $input['requested_amount']);
                $remaining = $this->creditChargeOutstanding($charge);
                if (bccomp($amount, '0.00', 2) <= 0 || bccomp($amount, $remaining, 2) > 0) {
                    throw ValidationException::withMessages(['allocations' => ["Repayment for invoice {$invoiceId} must be positive and cannot exceed its current credit balance of {$remaining}."]]);
                }
                $requestedTotal = bcadd($requestedTotal, $amount, 2);
            }
            $currency = (string) $charges->first()->currency;
            if ($charges->contains(fn (InvoiceCreditCharge $charge) => strtoupper($charge->currency) !== strtoupper($currency))) {
                throw ValidationException::withMessages(['allocations' => ['A single bank-transfer repayment must use one currency.']]);
            }
            $now = Carbon::now('UTC');
            $submission = VipCreditRepaymentSubmission::create([
                'organization_id' => $actor->organization_id,
                'customer_credit_account_id' => $account->id,
                'customer_id' => $customer->id,
                'proof_file_id' => $file->id,
                'submitted_by_user_id' => $actor->id,
                'source_key' => (string) Str::uuid(),
                'status' => VipCreditRepaymentSubmission::STATUS_SUBMITTED,
                'currency' => $currency,
                'requested_amount' => $requestedTotal,
                'declared_reference' => $data['declared_reference'] ?? null,
                'initial_submitted_at' => $now,
                'submitted_at' => $now,
                'lock_version' => 1,
            ]);
            foreach ($invoiceIds as $invoiceId) {
                $input = $inputs->get($invoiceId);
                VipCreditRepaymentAllocation::create([
                    'vip_credit_repayment_submission_id' => $submission->id,
                    'invoice_credit_charge_id' => $charges->get($invoiceId)->id,
                    'invoice_id' => $invoiceId,
                    'expected_invoice_lock_version' => $input['expected_invoice_lock_version'],
                    'requested_amount' => $this->decimal((string) $input['requested_amount']),
                ]);
            }
            VipCreditRepaymentProof::create([
                'vip_credit_repayment_submission_id' => $submission->id,
                'private_file_id' => $file->id,
                'private_file_version_number' => $fileVersion->version_number,
                'attempt_number' => 1,
                'submitted_by_user_id' => $actor->id,
                'created_at' => $now,
            ]);
            $this->accountEvent($account, $actor, 'REPAYMENT_PROOF_SUBMITTED', null, [
                'repayment_submission_id' => $submission->id, 'requested_amount' => $requestedTotal,
                'invoice_ids' => $invoiceIds,
            ], null, $submission);
            $this->auditRepayment($submission, $actor, 'VIP_CREDIT_REPAYMENT_SUBMITTED', 'proofs:upload', 'VIP customer submitted a bank-transfer proof for credit repayment.');

            return $this->loadRepayment($submission);
        });
    }

    /** @return LengthAwarePaginator<int, VipCreditRepaymentSubmission> */
    public function portalRepayments(User $actor, int $customerId)
    {
        $customer = $this->assertPortalAccess($actor, $customerId);

        return VipCreditRepaymentSubmission::where('organization_id', $actor->organization_id)
            ->where('customer_id', $customer->id)->where('submitted_by_user_id', $actor->id)
            ->with($this->repaymentRelations())->orderByDesc('initial_submitted_at')->paginate(20);
    }

    /** @return LengthAwarePaginator<int, VipCreditRepaymentSubmission> */
    public function tellerRepayments(User $actor, ?string $status = null)
    {
        $query = VipCreditRepaymentSubmission::where('organization_id', $actor->organization_id)->with($this->repaymentRelations())
            ->orderBy('initial_submitted_at')->orderBy('id');
        if ($status) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', [VipCreditRepaymentSubmission::STATUS_SUBMITTED, VipCreditRepaymentSubmission::STATUS_IN_REVIEW]);
        }

        return $query->paginate(25);
    }

    public function claimNextRepayment(User $teller): ?VipCreditRepaymentSubmission
    {
        return DB::transaction(function () use ($teller): ?VipCreditRepaymentSubmission {
            $submission = VipCreditRepaymentSubmission::where('organization_id', $teller->organization_id)
                ->where('status', VipCreditRepaymentSubmission::STATUS_SUBMITTED)
                ->orderBy('initial_submitted_at')->orderBy('id')->lock('FOR UPDATE SKIP LOCKED')->first();
            if (! $submission) {
                return null;
            }
            CustomerCreditAccount::whereKey($submission->customer_credit_account_id)->lockForUpdate()->firstOrFail();
            $now = Carbon::now('UTC');
            $submission->update([
                'status' => VipCreditRepaymentSubmission::STATUS_IN_REVIEW,
                'assigned_to_user_id' => $teller->id,
                'assigned_at' => $now,
                'lock_version' => $submission->lock_version + 1,
            ]);
            $this->accountEvent($submission->account, $teller, 'REPAYMENT_PROOF_CLAIMED', null, ['repayment_submission_id' => $submission->id], null, $submission);
            $this->auditRepayment($submission, $teller, 'VIP_CREDIT_REPAYMENT_CLAIMED', 'credit:review_proof', 'Authorized staff claimed the oldest VIP credit repayment proof.');

            return $this->loadRepayment($submission);
        });
    }

    /** @param array{expected_version:int,confirmed_reference:string,allocations:array<int,array{invoice_id:int,cash_amount:string}>} $data */
    public function approveRepayment(VipCreditRepaymentSubmission $submission, User $reviewer, array $data): VipCreditRepaymentSubmission
    {
        return DB::transaction(function () use ($submission, $reviewer, $data): VipCreditRepaymentSubmission {
            $locked = VipCreditRepaymentSubmission::where('organization_id', $reviewer->organization_id)->whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $allocations = VipCreditRepaymentAllocation::where('vip_credit_repayment_submission_id', $locked->id)
                ->orderBy('invoice_id')->lockForUpdate()->get();
            $account = CustomerCreditAccount::where('organization_id', $reviewer->organization_id)
                ->whereKey($locked->customer_credit_account_id)->lockForUpdate()->firstOrFail();
            $charges = InvoiceCreditCharge::whereIn('id', $allocations->pluck('invoice_credit_charge_id')->all())
                ->orderBy('invoice_id')->lockForUpdate()->get()->keyBy('id');
            $invoices = Invoice::where('organization_id', $reviewer->organization_id)->whereIn('id', $allocations->pluck('invoice_id')->all())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $fingerprint = $this->repaymentApprovalFingerprint($data['confirmed_reference'], $data['allocations']);
            if ($locked->status === VipCreditRepaymentSubmission::STATUS_APPROVED && $locked->receipt_id) {
                if (! hash_equals((string) $locked->approval_payload_fingerprint, $fingerprint)) {
                    throw ValidationException::withMessages(['submission' => ['This approved repayment cannot be retried with different allocation details.']]);
                }

                return $this->loadRepayment($locked);
            }
            $this->assertAssignedReview($locked, $reviewer, (int) $data['expected_version']);
            if (! $reviewer->hasPermission('receipts:post')) {
                throw new AuthorizationException('Receipt posting permission is required to approve a VIP repayment.');
            }
            $normalizedReference = $this->bankTransferReferences->normalize($data['confirmed_reference']);
            $postingAllocations = $this->repaymentPostingAllocations($allocations, $charges, $invoices, $data['allocations'], $data['confirmed_reference']);
            $this->bankTransferReferences->claim($reviewer->organization_id, $locked->currency, $data['confirmed_reference'], 'VIP_CREDIT_REPAYMENT', $locked->source_key);
            $receipt = $this->receiptPostingService->post($reviewer, [
                'source_type' => 'VIP_CREDIT_REPAYMENT',
                'source_key' => $locked->source_key,
                'customer_id' => $locked->customer_id,
                'currency' => $locked->currency,
                'payer_name' => $locked->customer->name,
                'receipt_kind' => $data['receipt_kind'] ?? Receipt::KIND_OFFICIAL,
                'allocations' => $postingAllocations,
            ]);
            $this->bankTransferReferences->linkReceipt($reviewer->organization_id, $locked->currency, $normalizedReference, $receipt);
            $now = Carbon::now('UTC');
            $locked->update([
                'status' => VipCreditRepaymentSubmission::STATUS_APPROVED,
                'receipt_id' => $receipt->id,
                'confirmed_reference' => $data['confirmed_reference'],
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => $now,
                'approval_payload_fingerprint' => $fingerprint,
                'lock_version' => $locked->lock_version + 1,
            ]);
            foreach ($allocations as $allocation) {
                $this->accountEvent($account, $reviewer, 'REPAYMENT_POSTED', null, [
                    'invoice_id' => $allocation->invoice_id, 'applied_amount' => $this->approvalAmount($data['allocations'], $allocation->invoice_id),
                    'receipt_number' => $receipt->receipt_number,
                ], $charges->get($allocation->invoice_credit_charge_id), $locked, $receipt);
            }
            $this->auditRepayment($locked, $reviewer, 'VIP_CREDIT_REPAYMENT_APPROVED', 'credit:review_proof', 'VIP bank transfer verified and posted through the shared collection-receipt flow.');
            $this->notifications->publish(
                (int) $locked->organization_id,
                (int) $locked->submitted_by_user_id,
                'CREDIT',
                'VIP Credit Repayment Confirmed',
                "Your bank repayment was verified. Collection receipt {$receipt->receipt_number} is available.",
                ['vip_credit_repayment_submission_id' => $locked->id, 'receipt_id' => $receipt->id, 'receipt_number' => $receipt->receipt_number],
            );
            $recipient = User::where('organization_id', $locked->organization_id)->whereKey($locked->submitted_by_user_id)->first();
            if ($recipient) {
                $this->dispatchVipTransactionalNotice(
                    $locked->organization_id,
                    'SETTLEMENT_POSTED',
                    'vip_credit_repayment_submission',
                    $locked->id,
                    $recipient,
                    [
                        'recipient_name' => $recipient->name,
                        'reference_no' => $receipt->receipt_number,
                        'action_label' => 'VIP credit repayment posted',
                        'date_formatted' => $now->copy()->setTimezone('Asia/Manila')->format('M d, Y'),
                        'org_name' => 'SCIPSI',
                    ],
                    $this->repaymentLocationIds($locked),
                );
            }

            return $this->loadRepayment($locked);
        });
    }

    public function rejectRepayment(VipCreditRepaymentSubmission $submission, User $reviewer, int $expectedVersion, string $reason): VipCreditRepaymentSubmission
    {
        return DB::transaction(function () use ($submission, $reviewer, $expectedVersion, $reason): VipCreditRepaymentSubmission {
            $locked = VipCreditRepaymentSubmission::where('organization_id', $reviewer->organization_id)->whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $this->assertAssignedReview($locked, $reviewer, $expectedVersion);
            $now = Carbon::now('UTC');
            $locked->update([
                'status' => VipCreditRepaymentSubmission::STATUS_REJECTED, 'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => $now, 'rejection_reason' => $reason, 'lock_version' => $locked->lock_version + 1,
            ]);
            $this->accountEvent($locked->account, $reviewer, 'REPAYMENT_PROOF_REJECTED', $reason, ['repayment_submission_id' => $locked->id], null, $locked);
            $this->auditRepayment($locked, $reviewer, 'VIP_CREDIT_REPAYMENT_REJECTED', 'credit:review_proof', 'VIP repayment proof rejected; no receipt or balance mutation occurred.');
            $this->notifications->publish(
                (int) $locked->organization_id,
                (int) $locked->submitted_by_user_id,
                'CREDIT',
                'VIP Repayment Proof Needs Correction',
                "Your repayment proof needs correction: {$reason}",
                ['vip_credit_repayment_submission_id' => $locked->id, 'reason' => $reason],
            );
            $recipient = User::where('organization_id', $locked->organization_id)->whereKey($locked->submitted_by_user_id)->first();
            if ($recipient) {
                $this->dispatchVipTransactionalNotice(
                    $locked->organization_id,
                    'VIP_REPAYMENT_REJECTED',
                    'vip_credit_repayment_submission',
                    $locked->id,
                    $recipient,
                    [
                        'recipient_name' => $recipient->name,
                        'reference_no' => 'VIP repayment',
                        'action_label' => 'VIP repayment proof needs correction',
                        'date_formatted' => $now->copy()->setTimezone('Asia/Manila')->format('M d, Y'),
                        'org_name' => 'SCIPSI',
                    ],
                    $this->repaymentLocationIds($locked),
                );
            }

            return $this->loadRepayment($locked);
        });
    }

    /** @param array{proof_file_id?:int} $data */
    public function resubmitRepayment(VipCreditRepaymentSubmission $submission, User $actor, array $data): VipCreditRepaymentSubmission
    {
        return DB::transaction(function () use ($submission, $actor, $data): VipCreditRepaymentSubmission {
            $locked = VipCreditRepaymentSubmission::where('organization_id', $actor->organization_id)->whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $this->assertPortalAccess($actor, $locked->customer_id, true);
            if ($locked->submitted_by_user_id !== $actor->id || $locked->status !== VipCreditRepaymentSubmission::STATUS_REJECTED) {
                throw new AuthorizationException('Only the submitting customer may resubmit a rejected VIP repayment proof.');
            }
            [$file, $fileVersion] = $this->lockUsableOwnedProof($actor, (int) ($data['proof_file_id'] ?? $locked->proof_file_id));
            if ($this->proofVersionIsUsed($file->id, $fileVersion->version_number)) {
                throw ValidationException::withMessages(['proof_file_id' => ['Upload a new clean proof file or a newer file version before resubmitting.']]);
            }
            $allocations = VipCreditRepaymentAllocation::where('vip_credit_repayment_submission_id', $locked->id)->orderBy('invoice_id')->lockForUpdate()->get();
            $charges = InvoiceCreditCharge::whereIn('id', $allocations->pluck('invoice_credit_charge_id')->all())->orderBy('invoice_id')->lockForUpdate()->get()->keyBy('id');
            foreach ($allocations as $allocation) {
                $charge = $charges->get($allocation->invoice_credit_charge_id);
                if (! $charge || bccomp($this->creditChargeOutstanding($charge), (string) $allocation->requested_amount, 2) < 0) {
                    throw ValidationException::withMessages(['allocations' => ['A selected credit bill changed or was paid elsewhere. Refresh your account and submit a new allocation.']]);
                }
            }
            $now = Carbon::now('UTC');
            $round = $locked->resubmission_rounds + 1;
            $locked->update([
                'proof_file_id' => $file->id, 'status' => VipCreditRepaymentSubmission::STATUS_SUBMITTED,
                'submitted_at' => $now, 'assigned_to_user_id' => null, 'assigned_at' => null,
                'rejection_reason' => null, 'resubmission_rounds' => $round, 'lock_version' => $locked->lock_version + 1,
            ]);
            VipCreditRepaymentProof::create([
                'vip_credit_repayment_submission_id' => $locked->id, 'private_file_id' => $file->id,
                'private_file_version_number' => $fileVersion->version_number, 'attempt_number' => $round + 1,
                'submitted_by_user_id' => $actor->id, 'created_at' => $now,
            ]);
            $this->accountEvent($locked->account, $actor, 'REPAYMENT_PROOF_RESUBMITTED', null, ['repayment_submission_id' => $locked->id, 'round' => $round], null, $locked);
            $this->auditRepayment($locked, $actor, 'VIP_CREDIT_REPAYMENT_RESUBMITTED', 'proofs:upload', 'Customer resubmitted a corrected VIP repayment proof with original queue priority.');

            return $this->loadRepayment($locked);
        });
    }

    protected function summary(CustomerCreditAccount $account, Carbon $at): array
    {
        $account->loadMissing('customer');
        $policy = $this->effectivePolicy($account->organization_id, $at);
        $profile = $this->effectiveAccountVersion($account->id, $at);
        if (! $policy || ! $profile) {
            return ['eligible' => false, 'reason' => 'Credit configuration is incomplete or not effective.', 'account' => $account];
        }
        $terms = $this->resolveTerms($policy, $profile);
        $charges = InvoiceCreditCharge::where('customer_credit_account_id', $account->id)->with('invoice')->orderBy('due_date')->orderBy('id')->get();
        $applied = $this->postedAllocationMap($charges->pluck('invoice_id')->all());
        $exposure = '0.00';
        $overdue = '0.00';
        $items = $charges->map(function (InvoiceCreditCharge $charge) use ($applied, $at, &$exposure, &$overdue): array {
            $outstanding = $this->positiveDifference((string) $charge->charged_amount, $applied[$charge->invoice_id] ?? '0.00');
            $exposure = bcadd($exposure, $outstanding, 2);
            $isOverdue = $charge->due_date && $outstanding !== '0.00' && $charge->due_date->copy()->addDays((int) ($charge->terms_snapshot['overdue_grace_days'] ?? 0))->lt($at->copy()->startOfDay());
            if ($isOverdue) {
                $overdue = bcadd($overdue, $outstanding, 2);
            }

            return [
                'credit_charge_id' => $charge->id, 'invoice_id' => $charge->invoice_id,
                'invoice_number' => $charge->invoice?->invoice_number, 'currency' => $charge->currency,
                'charged_amount' => (string) $charge->charged_amount, 'outstanding_amount' => $outstanding,
                'due_date' => $charge->due_date?->toDateString(), 'due_date_classification' => $charge->due_date ? 'CLASSIFIED' : 'UNCLASSIFIED_NEEDS_TERMS_REVIEW',
                'charged_at' => $charge->charged_at?->toIso8601String(),
                'payment_terms_days' => $charge->payment_terms_days_snapshot,
                'due_date_basis' => $charge->due_date_basis_snapshot, 'is_overdue' => $isOverdue,
                'invoice_lock_version' => $charge->invoice?->lock_version,
            ];
        })->values()->all();
        $available = $this->availableCredit($terms, $exposure);

        return [
            'eligible' => $profile->status === CustomerCreditAccountVersion::STATUS_ACTIVE,
            'reason' => $profile->status === CustomerCreditAccountVersion::STATUS_ACTIVE ? null : 'New VIP credit charges are disabled for this account; repayment remains available.',
            'account' => ['id' => $account->id, 'customer_id' => $account->customer_id, 'customer_name' => $account->customer?->name, 'lock_version' => $account->lock_version],
            'profile' => ['version' => $profile->version_number, 'status' => $profile->status, 'effective_from' => $profile->effective_from->toIso8601String()],
            'policy' => ['version' => $policy->version_number, 'currency' => $policy->currency, 'effective_from' => $policy->effective_from->toIso8601String()],
            'terms' => $terms,
            'exposure_amount' => $exposure, 'overdue_amount' => $overdue, 'available_credit_amount' => $available,
            'charges' => $items,
        ];
    }

    protected function effectivePolicy(int $organizationId, Carbon $at, bool $lock = false): ?CreditPolicyVersion
    {
        $query = CreditPolicyVersion::where('organization_id', $organizationId)->where('status', CreditPolicyVersion::STATUS_PUBLISHED)
            ->where('effective_from', '<=', $at)->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $at))
            ->orderByDesc('version_number');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    protected function effectiveAccountVersion(int $accountId, Carbon $at, bool $lock = false): ?CustomerCreditAccountVersion
    {
        $query = CustomerCreditAccountVersion::where('customer_credit_account_id', $accountId)->where('effective_from', '<=', $at)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $at))->orderByDesc('version_number');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /** @return array<string,mixed> */
    protected function resolveTerms(CreditPolicyVersion $policy, CustomerCreditAccountVersion $profile): array
    {
        $terms = [
            'credit_limit_mode' => $profile->credit_limit_mode_override ?? $policy->default_credit_limit_mode,
            'credit_limit_amount' => $profile->credit_limit_amount_override ?? $policy->default_credit_limit_amount,
            'payment_terms_days' => $profile->payment_terms_days_override ?? $policy->payment_terms_days,
            'due_date_basis' => $profile->due_date_basis_override ?? $policy->due_date_basis,
            'overdue_restriction' => $profile->overdue_restriction_override ?? $policy->overdue_restriction,
            'overdue_grace_days' => $profile->overdue_grace_days_override ?? $policy->overdue_grace_days,
            'overdue_amount_threshold' => $profile->overdue_amount_threshold_override ?? $policy->overdue_amount_threshold,
            'currency' => $policy->currency,
        ];
        if ($terms['credit_limit_mode'] === 'CAPPED' && $terms['credit_limit_amount'] === null) {
            throw ValidationException::withMessages(['credit_account' => ['The resolved capped credit limit is incomplete.']]);
        }

        return $terms;
    }

    protected function assertNoOverdueBlock(CustomerCreditAccount $account, array $terms, Carbon $at): void
    {
        if ($terms['overdue_restriction'] !== 'BLOCK') {
            return;
        }
        $charges = InvoiceCreditCharge::where('customer_credit_account_id', $account->id)->where('currency', $terms['currency'])->get();
        $applied = $this->postedAllocationMap($charges->pluck('invoice_id')->all());
        $overdue = '0.00';
        $unclassified = '0.00';
        foreach ($charges as $charge) {
            $outstanding = $this->positiveDifference((string) $charge->charged_amount, $applied[$charge->invoice_id] ?? '0.00');
            if (! $charge->due_date) {
                $unclassified = bcadd($unclassified, $outstanding, 2);

                continue;
            }
            if ($charge->due_date->copy()->addDays((int) $terms['overdue_grace_days'])->lt($at->copy()->startOfDay())) {
                $overdue = bcadd($overdue, $outstanding, 2);
            }
        }
        if (bccomp($unclassified, '0.00', 2) > 0) {
            throw ValidationException::withMessages(['credit_account' => ['New VIP credit charges are blocked until existing credit with missing due-date terms is reviewed.']]);
        }
        if (bccomp($overdue, '0.00', 2) > 0 && ($terms['overdue_amount_threshold'] === null || bccomp($overdue, (string) $terms['overdue_amount_threshold'], 2) >= 0)) {
            throw ValidationException::withMessages(['credit_account' => ['New VIP credit charges are blocked by the configured overdue restriction. Existing credit debt remains repayable.']]);
        }
    }

    protected function creditExposure(int $accountId, string $currency, bool $lock = false): string
    {
        $query = InvoiceCreditCharge::where('customer_credit_account_id', $accountId)->where('currency', strtoupper($currency))->orderBy('invoice_id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $charges = $query->get();
        $applied = $this->postedAllocationMap($charges->pluck('invoice_id')->all());
        $total = '0.00';
        foreach ($charges as $charge) {
            $total = bcadd($total, $this->positiveDifference((string) $charge->charged_amount, $applied[$charge->invoice_id] ?? '0.00'), 2);
        }

        return $total;
    }

    protected function creditChargeOutstanding(InvoiceCreditCharge $charge): string
    {
        $applied = $this->postedAllocationMap([$charge->invoice_id]);

        return $this->positiveDifference((string) $charge->charged_amount, $applied[$charge->invoice_id] ?? '0.00');
    }

    /** @param array<int,int> $invoiceIds @return array<int,string> */
    protected function postedAllocationMap(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        return ReceiptAllocation::whereIn('invoice_id', $invoiceIds)->whereHas('receipt', fn ($q) => $q->where('status', 'POSTED'))
            ->select('invoice_id', DB::raw('SUM(applied_amount) as amount'))->groupBy('invoice_id')->pluck('amount', 'invoice_id')->map(fn ($amount) => (string) $amount)->all();
    }

    protected function invoiceOutstanding(int $invoiceId, string $total): string
    {
        $applied = $this->postedAllocationMap([$invoiceId]);

        return $this->positiveDifference($total, $applied[$invoiceId] ?? '0.00');
    }

    /** @param array<int,int> $invoiceIds */
    protected function assertNoPendingProofSelection(array $invoiceIds): void
    {
        $regular = ManualPaymentSubmissionItem::whereIn('invoice_id', $invoiceIds)->whereHas('submission', fn ($q) => $q->whereIn('status', ['SUBMITTED', 'IN_REVIEW']))->lockForUpdate()->exists();
        $vip = VipCreditRepaymentAllocation::whereIn('invoice_id', $invoiceIds)->whereHas('submission', fn ($q) => $q->whereIn('status', ['SUBMITTED', 'IN_REVIEW']))->lockForUpdate()->exists();
        if ($regular || $vip) {
            throw ValidationException::withMessages(['allocations' => ['A selected bill already has a payment proof awaiting review. Refresh after that review is decided.']]);
        }
    }

    /** @return array{0:PrivateFile,1:PrivateFileVersion} */
    protected function lockUsableOwnedProof(User $actor, int $fileId): array
    {
        $file = PrivateFile::where('organization_id', $actor->organization_id)->whereKey($fileId)->lockForUpdate()->firstOrFail();
        if ($file->purpose !== 'PAYMENT_PROOF' || $file->status !== 'CLEAN' || ($file->owner_id !== $actor->id && $file->uploaded_by !== $actor->id)) {
            throw ValidationException::withMessages(['proof_file_id' => ['Select a clean payment proof that you uploaded or own.']]);
        }
        $version = PrivateFileVersion::where('private_file_id', $file->id)->where('version_number', $file->current_version)->lockForUpdate()->first();
        if (! $version || $version->scan_status !== 'CLEAN') {
            throw ValidationException::withMessages(['proof_file_id' => ['The selected payment proof version is not clean and available for review.']]);
        }

        return [$file, $version];
    }

    protected function proofVersionIsUsed(int $fileId, int $version): bool
    {
        return VipCreditRepaymentProof::where('private_file_id', $fileId)->where('private_file_version_number', $version)->exists()
            || ManualPaymentSubmissionProof::where('private_file_id', $fileId)->where('private_file_version_number', $version)->exists();
    }

    /** @param Collection<int,VipCreditRepaymentAllocation> $stored @param Collection<int,InvoiceCreditCharge> $charges @param Collection<int,Invoice> $invoices @param array<int,array<string,mixed>> $decisions @return array<int,array<string,mixed>> */
    protected function repaymentPostingAllocations($stored, $charges, $invoices, array $decisions, string $reference): array
    {
        $byInvoice = collect($decisions)->map(function (array $row): array {
            $row['invoice_id'] = (int) $row['invoice_id'];
            $row['cash_amount'] = $this->decimal((string) $row['cash_amount']);

            return $row;
        })->keyBy('invoice_id');
        if ($byInvoice->count() !== count($decisions) || $byInvoice->keys()->sort()->values()->all() !== $stored->pluck('invoice_id')->sort()->values()->all()) {
            throw ValidationException::withMessages(['allocations' => ['The reviewer must decide every submitted credit allocation exactly once.']]);
        }
        $result = [];
        foreach ($stored as $item) {
            $invoice = $invoices->get($item->invoice_id);
            $charge = $charges->get($item->invoice_credit_charge_id);
            $row = $byInvoice->get($item->invoice_id);
            if (! $invoice || ! $charge || $invoice->lock_version !== $item->expected_invoice_lock_version
                || bccomp($this->creditChargeOutstanding($charge), (string) $item->requested_amount, 2) < 0) {
                throw ValidationException::withMessages(['allocations' => ['A selected credit bill changed or was paid elsewhere. Resolve the conflict without posting a partial repayment.']]);
            }
            if (bccomp($row['cash_amount'], '0.00', 2) <= 0 || bccomp($row['cash_amount'], (string) $item->requested_amount, 2) > 0) {
                throw ValidationException::withMessages(['allocations' => ["Confirmed repayment for invoice {$item->invoice_id} must be positive and cannot exceed the customer allocation."]]);
            }
            $result[] = [
                'invoice_id' => $item->invoice_id, 'expected_invoice_lock_version' => $item->expected_invoice_lock_version,
                'tenders' => [['type' => 'BANK_TRANSFER', 'status' => 'CONFIRMED', 'amount' => $row['cash_amount'], 'reference' => $reference]],
            ];
        }

        return $result;
    }

    protected function assertAssignedReview(VipCreditRepaymentSubmission $submission, User $reviewer, int $expectedVersion): void
    {
        if ($submission->status !== VipCreditRepaymentSubmission::STATUS_IN_REVIEW || $submission->assigned_to_user_id !== $reviewer->id) {
            throw new AuthorizationException('Only the assigned reviewer may decide this VIP repayment proof.');
        }
        if ($submission->lock_version !== $expectedVersion) {
            throw new ConcurrencyException('VIP repayment changed since it was loaded. Refresh before deciding.');
        }
    }

    protected function availableCredit(array $terms, string $exposure): ?string
    {
        if ($terms['credit_limit_mode'] === 'UNLIMITED') {
            return null;
        }
        $remaining = bcsub((string) $terms['credit_limit_amount'], $exposure, 2);

        return bccomp($remaining, '0.00', 2) > 0 ? $remaining : '0.00';
    }

    protected function assertPortalAccess(User $actor, int $customerId, bool $lock = false): Customer
    {
        $customer = Customer::where('organization_id', $actor->organization_id)->whereKey($customerId)->firstOrFail();
        if (! $customer->isActive()) {
            throw new AuthorizationException('This customer account is not active.');
        }
        $link = CustomerUserLink::where('customer_id', $customer->id)->where('user_id', $actor->id)->where('is_active', true);
        if ($lock) {
            $link->lockForUpdate();
        }
        if (! $link->exists()) {
            throw new AuthorizationException('An active customer-account link is required.');
        }

        return $customer;
    }

    protected function assertVipCustomer(Customer $customer): void
    {
        if (strtolower((string) $customer->customer_type) !== 'vip') {
            throw ValidationException::withMessages(['customer_id' => ['Only a customer explicitly classified as VIP can use a credit account.']]);
        }
    }

    /** @param array<string,mixed> $data */
    protected function assertPolicyShape(array $data): void
    {
        $mode = strtoupper((string) $data['default_credit_limit_mode']);
        if (! in_array($mode, ['CAPPED', 'UNLIMITED'], true) || ($mode === 'CAPPED' && ! isset($data['default_credit_limit_amount']))) {
            throw ValidationException::withMessages(['default_credit_limit_mode' => ['A credit policy needs either a non-negative capped limit or an explicit unlimited mode.']]);
        }
    }

    /** @param array<string,mixed> $data */
    protected function assertOverrideShape(array $data): void
    {
        $mode = $this->nullableUpper($data['credit_limit_mode_override'] ?? null);
        if ($mode !== null && ! in_array($mode, ['CAPPED', 'UNLIMITED'], true)) {
            throw ValidationException::withMessages(['credit_limit_mode_override' => ['Override must be CAPPED or UNLIMITED.']]);
        }
        if ($mode === 'CAPPED' && ! isset($data['credit_limit_amount_override'])) {
            throw ValidationException::withMessages(['credit_limit_amount_override' => ['A capped customer override needs an explicit amount.']]);
        }
    }

    protected function preparePublishedCreditPolicyWindow(CreditPolicyVersion $candidate): void
    {
        $cutover = Carbon::now('Asia/Manila');
        if ($candidate->effective_from) {
            $from = $candidate->effective_from->copy()->timezone('Asia/Manila');
            if ($from->greaterThan($cutover)) {
                $cutover = $from;
            }
        }

        // End every open published window that already started, then ensure cutover is after
        // any published start that would still block an open-ended draft.
        $safety = 0;
        while ($safety < 10) {
            $safety++;
            $openEnded = CreditPolicyVersion::where('organization_id', $candidate->organization_id)
                ->where('status', CreditPolicyVersion::STATUS_PUBLISHED)
                ->whereNull('effective_to')
                ->whereKeyNot($candidate->id)
                ->orderBy('effective_from')
                ->lockForUpdate()
                ->get();

            if ($openEnded->isEmpty()) {
                break;
            }

            $closedAny = false;
            foreach ($openEnded as $policy) {
                $policyStart = $policy->effective_from->copy()->timezone('Asia/Manila');
                if ($policyStart->lessThan($cutover)) {
                    $policy->forceFill([
                        'effective_to' => $cutover,
                        'lock_version' => $policy->lock_version + 1,
                    ])->save();
                    $closedAny = true;
                }
            }

            if ($closedAny) {
                continue;
            }

            // Remaining open policies start at/after cutover — advance cutover past the latest.
            $latestStart = $openEnded
                ->map(fn (CreditPolicyVersion $policy) => $policy->effective_from->copy()->timezone('Asia/Manila')->getTimestamp())
                ->max();
            $cutover = Carbon::createFromTimestamp((int) $latestStart, 'Asia/Manila')->addSecond();
        }

        $candidate->forceFill(['effective_from' => $cutover])->save();
        $candidate->refresh();
    }

    protected function assertNoPublishedOverlap(CreditPolicyVersion $candidate): void
    {
        $candidateFrom = $candidate->effective_from->copy()->timezone('Asia/Manila');
        $candidateTo = $candidate->effective_to?->copy()->timezone('Asia/Manila');
        $published = CreditPolicyVersion::where('organization_id', $candidate->organization_id)
            ->where('status', CreditPolicyVersion::STATUS_PUBLISHED)
            ->lockForUpdate()
            ->get();
        foreach ($published as $policy) {
            $policyFrom = $policy->effective_from->copy()->timezone('Asia/Manila');
            $policyTo = $policy->effective_to?->copy()->timezone('Asia/Manila');
            $candidateBefore = $candidateTo && $candidateTo->lessThanOrEqualTo($policyFrom);
            $policyBefore = $policyTo && $policyTo->lessThanOrEqualTo($candidateFrom);
            if (! $candidateBefore && ! $policyBefore) {
                throw ValidationException::withMessages(['effective_from' => ["Credit policy window overlaps published version {$policy->version_number}."]]);
            }
        }
    }

    /** @param array<string,mixed> $data @return array<int,string> */
    protected function overrideFields(array $data): array
    {
        return collect([
            'credit_limit_mode_override', 'credit_limit_amount_override', 'payment_terms_days_override',
            'due_date_basis_override', 'overdue_restriction_override', 'overdue_grace_days_override', 'overdue_amount_threshold_override',
        ])->filter(fn (string $key) => array_key_exists($key, $data) && $data[$key] !== null)->all();
    }

    protected function loadAccount(CustomerCreditAccount $account): CustomerCreditAccount
    {
        return $account->fresh(['customer', 'versions' => fn ($q) => $q->orderByDesc('version_number'), 'events.actor:id,name,email']);
    }

    protected function loadRepayment(VipCreditRepaymentSubmission $submission): VipCreditRepaymentSubmission
    {
        return $submission->fresh($this->repaymentRelations());
    }

    /** @return array<int,string> */
    protected function repaymentRelations(): array
    {
        return ['account.customer', 'proofFile.latestVersion', 'receipt.canonicalArtifact', 'submittedBy:id,name,email',
            'assignedTeller:id,name,email', 'reviewer:id,name,email', 'allocations.invoice', 'allocations.charge', 'proofs.privateFile.latestVersion'];
    }

    protected function decimal(string $amount): string
    {
        return bcadd($amount, '0.00', 2);
    }

    protected function nullableDecimal(mixed $amount): ?string
    {
        return $amount === null || $amount === '' ? null : $this->decimal((string) $amount);
    }

    protected function nullableUpper(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : strtoupper((string) $value);
    }

    protected function positiveDifference(string $total, string $applied): string
    {
        $value = bcsub($total, $applied, 2);

        return bccomp($value, '0.00', 2) > 0 ? $value : '0.00';
    }

    /**
     * Select the customer owner first, then a deterministic active portal link. Account
     * notifications are never sent to staff merely because they changed the profile.
     */
    protected function primaryPortalRecipient(Customer $customer): ?User
    {
        $link = CustomerUserLink::where('customer_id', $customer->id)->where('is_active', true)
            ->orderByRaw("CASE WHEN authority_role = 'owner' THEN 0 ELSE 1 END")
            ->orderBy('id')->first();
        if (! $link) {
            return null;
        }

        return User::where('organization_id', $customer->organization_id)->whereKey($link->user_id)->first();
    }

    /**
     * Records an authoritative, post-commit event and lets the SMS outbox decide whether a
     * verified contact and enabled policy permit delivery. It never dispatches a provider call
     * and failures in this non-financial path cannot undo a credit or receipt transaction.
     *
     * @param  array<string,mixed>  $payload
     * @param  array<int,int>  $locationIds
     */
    protected function dispatchVipTransactionalNotice(
        int $organizationId,
        string $eventKey,
        string $eventSourceType,
        int $eventSourceId,
        User $recipient,
        array $payload,
        array $locationIds = [],
    ): void {
        $occurredAt = Carbon::now('Asia/Manila');
        $dispatch = function () use ($organizationId, $eventKey, $eventSourceType, $eventSourceId, $recipient, $payload, $locationIds, $occurredAt): void {
            try {
                $event = $this->notificationEvents->record([
                    'organization_id' => $organizationId,
                    'event_key' => $eventKey,
                    'event_source_type' => $eventSourceType,
                    'event_source_id' => $eventSourceId,
                    'user_id' => $recipient->id,
                    'payload_snapshot' => $payload,
                    'occurred_at' => $occurredAt,
                ], $locationIds);
                $this->smsOrchestrator->queueIntent($event);
            } catch (\Throwable $e) {
                report($e);
            }
        };

        if (DB::transactionLevel() > 0 && ! app()->runningUnitTests()) {
            DB::afterCommit($dispatch);
        } else {
            $dispatch();
        }
    }

    /** @return array<int,int> */
    protected function repaymentLocationIds(VipCreditRepaymentSubmission $submission): array
    {
        return VipCreditRepaymentAllocation::query()
            ->join('invoices', 'invoices.id', '=', 'vip_credit_repayment_allocations.invoice_id')
            ->where('vip_credit_repayment_allocations.vip_credit_repayment_submission_id', $submission->id)
            ->whereNotNull('invoices.location_id')
            ->pluck('invoices.location_id')
            ->map(static fn ($locationId): int => (int) $locationId)
            ->unique()
            ->values()
            ->all();
    }

    /** @param array<string,mixed> $metadata */
    protected function accountEvent(CustomerCreditAccount $account, ?User $actor, string $type, ?string $reason, array $metadata, ?InvoiceCreditCharge $charge = null, ?VipCreditRepaymentSubmission $repayment = null, ?Receipt $receipt = null): void
    {
        CreditAccountEvent::create(['customer_credit_account_id' => $account->id, 'invoice_credit_charge_id' => $charge?->id,
            'vip_credit_repayment_submission_id' => $repayment?->id, 'receipt_id' => $receipt?->id, 'actor_id' => $actor?->id,
            'event_type' => $type, 'reason' => $reason, 'metadata' => $metadata, 'created_at' => Carbon::now('Asia/Manila')]);
    }

    protected function auditPolicy(CreditPolicyVersion $policy, User $actor, string $type, string $permission, string $reason): void
    {
        AuditEvent::create(['organization_id' => $policy->organization_id, 'event_type' => $type, 'aggregate_type' => 'CREDIT_POLICY_VERSION',
            'aggregate_id' => $policy->id, 'aggregate_version' => $policy->lock_version, 'actor_type' => 'user', 'actor_id' => $actor->id,
            'permission_snapshot' => $permission, 'occurred_at' => Carbon::now('Asia/Manila'), 'reason' => $reason,
            'metadata' => ['version_number' => $policy->version_number, 'status' => $policy->status]]);
    }

    protected function auditAccount(CustomerCreditAccount $account, User $actor, string $type, string $permission, string $reason): void
    {
        AuditEvent::create(['organization_id' => $account->organization_id, 'event_type' => $type, 'aggregate_type' => 'CUSTOMER_CREDIT_ACCOUNT',
            'aggregate_id' => $account->id, 'aggregate_version' => $account->lock_version, 'actor_type' => 'user', 'actor_id' => $actor->id,
            'permission_snapshot' => $permission, 'occurred_at' => Carbon::now('Asia/Manila'), 'reason' => $reason,
            'metadata' => ['customer_id' => $account->customer_id]]);
    }

    protected function auditRepayment(VipCreditRepaymentSubmission $submission, User $actor, string $type, string $permission, string $reason): void
    {
        AuditEvent::create(['organization_id' => $submission->organization_id, 'event_type' => $type, 'aggregate_type' => 'VIP_CREDIT_REPAYMENT_SUBMISSION',
            'aggregate_id' => $submission->id, 'aggregate_version' => $submission->lock_version, 'actor_type' => 'user', 'actor_id' => $actor->id,
            'permission_snapshot' => $permission, 'occurred_at' => Carbon::now('Asia/Manila'), 'reason' => $reason,
            'metadata' => ['status' => $submission->status, 'source_key' => $submission->source_key, 'receipt_id' => $submission->receipt_id]]);
    }

    /** @param array<int,array<string,mixed>> $decisions */
    protected function approvalAmount(array $decisions, int $invoiceId): string
    {
        return $this->decimal((string) collect($decisions)->firstWhere('invoice_id', $invoiceId)['cash_amount']);
    }

    /** @param array<int,array<string,mixed>> $decisions */
    protected function repaymentApprovalFingerprint(string $reference, array $decisions): string
    {
        $normalized = collect($decisions)->map(fn (array $row) => [
            'invoice_id' => (int) $row['invoice_id'],
            'cash_amount' => $this->decimal((string) $row['cash_amount']),
        ])->sortBy('invoice_id')->values()->all();

        return hash('sha256', (string) json_encode([
            'confirmed_reference' => $this->bankTransferReferences->normalize($reference),
            'allocations' => $normalized,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
