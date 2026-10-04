<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BuyerProfileVersion;
use App\Models\Customer;
use App\Models\CustomerBuyerProfile;
use App\Models\CustomerTaxExemption;
use App\Models\CustomerWithholdingCertificate;
use App\Models\PrivateFile;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CustomerAccountAdminController extends Controller
{
    public const VIP_ACCOUNT_NUMBER_PREFIX = 'VIP-';

    public const VIP_ACCOUNT_NUMBER_PAD = 6;

    /**
     * Suggest the next unused VIP account number for the actor's organization.
     * Does not create or reserve a customer; create/update still enforce uniqueness.
     */
    public function nextAccountNumber(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $organizationId = (int) $actor->organization_id;

        $usedInOrganization = Customer::query()
            ->where('organization_id', $organizationId)
            ->where('account_number', '~', '^VIP-[0-9]+$')
            ->pluck('account_number');

        $takenSequences = [];
        foreach ($usedInOrganization as $accountNumber) {
            if (preg_match('/^VIP-0*([0-9]+)$/', (string) $accountNumber, $matches) === 1) {
                $takenSequences[(int) $matches[1]] = true;
            }
        }

        $sequence = 1;
        while (isset($takenSequences[$sequence])) {
            $sequence++;
        }

        // Account numbers are globally unique; skip any collision outside this org.
        while (true) {
            $candidate = self::VIP_ACCOUNT_NUMBER_PREFIX.str_pad(
                (string) $sequence,
                self::VIP_ACCOUNT_NUMBER_PAD,
                '0',
                STR_PAD_LEFT
            );
            $exists = Customer::query()->where('account_number', $candidate)->exists();
            if (! $exists) {
                return response()->json([
                    'account_number' => $candidate,
                ]);
            }
            $sequence++;
        }
    }

    /**
     * List customer business accounts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query()->with([
            'buyerProfile.versions' => function ($q) {
                $q->orderByDesc('version');
            },
            'userLinks.user',
            'contactPoints',
            'withholdingCertificates' => fn ($q) => $q->orderByDesc('id'),
            'taxExemptions' => fn ($q) => $q->orderByDesc('id'),
        ]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('account_number', 'ilike', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($customerType = $request->input('customer_type')) {
            $query->where('customer_type', $customerType);
        }

        $customers = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        $data = collect($customers->items())
            ->map(fn (Customer $customer) => $this->presentCustomer($customer, includeTaxDetails: false))
            ->values()
            ->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    /**
     * Create a customer business account with an explicit account number.
     * Does not create a portal user or auto-link by name/phone/e-mail.
     */
    public function store(Request $request): JsonResponse
    {
        $this->normalizeIdentityInput($request);
        $isVip = $this->requestIsVip($request);
        if (! $isVip && trim((string) $request->input('account_number')) === '') {
            $request->merge(['account_number' => Customer::NON_VIP_ACCOUNT_NUMBER]);
        }

        $validated = $request->validate([
            'account_number' => $this->accountNumberRules(isVip: $isVip),
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,pending,suspended,archived'],
            'customer_type' => ['nullable', 'string', 'in:business,walk_in,vip'],
            'is_vip' => ['sometimes', 'boolean'],
        ], $this->accountNumberMessages());

        /** @var User $actor */
        $actor = $request->user();

        $customer = DB::transaction(function () use ($validated, $actor, $isVip) {
            $customer = Customer::create([
                'organization_id' => $actor->organization_id,
                'account_number' => $validated['account_number'],
                'name' => $validated['name'],
                'status' => $validated['status'] ?? 'active',
                'customer_type' => $isVip ? 'vip' : ($validated['customer_type'] ?? 'business'),
                'lock_version' => 1,
            ]);

            $buyerProfile = CustomerBuyerProfile::create([
                'customer_id' => $customer->id,
                'current_version' => 1,
                'is_active' => true,
            ]);

            BuyerProfileVersion::create([
                'buyer_profile_id' => $buyerProfile->id,
                'version' => 1,
                'registered_name' => $validated['name'],
                'status' => 'active',
                'effective_from' => now(),
                'created_by_user_id' => $actor->id,
            ]);

            return $customer;
        });

        AuditLogger::log(
            action: 'customer.created',
            auditable: $customer,
            oldValues: null,
            newValues: [
                'account_number' => $customer->account_number,
                'name' => $customer->name,
                'status' => $customer->status,
                'customer_type' => $customer->customer_type,
            ],
            actor: $actor,
            organizationId: $customer->organization_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Customer account created.',
            'customer' => $customer->fresh(['buyerProfile.versions', 'userLinks.user', 'contactPoints']),
        ], 201);
    }

    /**
     * Update customer identity fields. Issued invoice/receipt snapshots stay unchanged.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->normalizeIdentityInput($request);

        $customer = Customer::findOrFail($id);
        $isVip = $request->exists('is_vip') || $request->exists('customer_type')
            ? $this->requestIsVip($request)
            : $customer->isVip();
        $validated = $request->validate([
            'account_number' => $this->accountNumberRules($customer->id, $isVip),
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'customer_type' => ['sometimes', 'string', 'in:business,walk_in,vip'],
            'is_vip' => ['sometimes', 'boolean'],
        ], $this->accountNumberMessages());

        $oldValues = [
            'account_number' => $customer->account_number,
            'name' => $customer->name,
            'customer_type' => $customer->customer_type,
        ];

        unset($validated['is_vip']);
        if ($request->exists('is_vip')) {
            $validated['customer_type'] = $isVip ? 'vip' : ($validated['customer_type'] ?? 'business');
        }

        $customer->fill($validated);
        $customer->lock_version = $customer->lock_version + 1;
        $customer->save();

        /** @var User $actor */
        $actor = $request->user();

        AuditLogger::log(
            action: 'customer.identity_updated',
            auditable: $customer,
            oldValues: $oldValues,
            newValues: [
                'account_number' => $customer->account_number,
                'name' => $customer->name,
                'customer_type' => $customer->customer_type,
            ],
            actor: $actor,
            organizationId: $customer->organization_id
        );

        return response()->json([
            'success' => true,
            'message' => 'Customer account updated.',
            'customer' => $customer->fresh(['buyerProfile.versions', 'userLinks.user', 'contactPoints']),
        ]);
    }

    /**
     * Get customer business account detail.
     */
    public function show(int $id): JsonResponse
    {
        $customer = Customer::with([
            'buyerProfile.versions.createdBy',
            'buyerProfile.versions.reviewedBy',
            'userLinks.user',
            'contactPoints.verificationEvents',
            'withholdingCertificates' => fn ($q) => $q->orderByDesc('id'),
            'withholdingCertificates.privateFile.latestVersion',
            'taxExemptions' => fn ($q) => $q->orderByDesc('id'),
            'taxExemptions.privateFile.latestVersion',
        ])->findOrFail($id);

        return response()->json([
            'customer' => $this->presentCustomer($customer, includeTaxDetails: true),
        ]);
    }

    /**
     * Update customer account status (e.g. active, suspended).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:active,pending,suspended,archived',
            'notes' => 'nullable|string|max:500',
        ]);

        $customer = Customer::findOrFail($id);
        $oldStatus = $customer->status;

        $customer->update(['status' => $validated['status']]);

        /** @var User $actor */
        $actor = $request->user();

        AuditLogger::log(
            action: 'customer.status_updated',
            auditable: $customer,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $validated['status'], 'notes' => $validated['notes'] ?? null],
            actor: $actor,
            organizationId: $customer->organization_id
        );

        return response()->json([
            'success' => true,
            'message' => "Customer status updated to {$validated['status']}.",
            'customer' => $customer,
        ]);
    }

    /**
     * List buyer profile versions for a customer.
     */
    public function buyerProfiles(int $id): JsonResponse
    {
        $customer = Customer::with('buyerProfile.versions.createdBy', 'buyerProfile.versions.reviewedBy')
            ->findOrFail($id);

        return response()->json([
            'buyer_profile' => $customer->buyerProfile,
            'versions' => $customer->buyerProfile?->versions ?? [],
        ]);
    }

    /**
     * Review / approve / reject a buyer profile version.
     */
    public function reviewVersion(Request $request, int $customerId, int $versionId): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:approve,reject',
            'review_notes' => 'nullable|string|max:500',
        ]);

        $customer = Customer::findOrFail($customerId);
        $version = BuyerProfileVersion::where('buyer_profile_id', $customer->buyerProfile?->id)
            ->findOrFail($versionId);

        /** @var User $actor */
        $actor = $request->user();

        $oldStatus = $version->status;
        $newStatus = $validated['action'] === 'approve' ? 'active' : 'rejected';

        $version->update([
            'status' => $newStatus,
            'reviewed_by_user_id' => $actor->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'] ?? null,
        ]);

        if ($newStatus === 'active') {
            $customer->buyerProfile->update(['current_version' => $version->version]);
        }

        AuditLogger::log(
            action: "buyer_profile.version_{$validated['action']}d",
            auditable: $version,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus, 'review_notes' => $validated['review_notes'] ?? null],
            actor: $actor,
            organizationId: $customer->organization_id
        );

        return response()->json([
            'success' => true,
            'message' => "Buyer profile version {$version->version} {$newStatus}.",
            'version' => $version,
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function accountNumberRules(?int $ignoreId = null, bool $isVip = false): array
    {
        $unique = Rule::unique('customers', 'account_number');
        if ($ignoreId !== null) {
            $unique = $unique->ignore($ignoreId);
        }

        $rules = [
            'required',
            'string',
            'max:64',
            'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]{0,63}$/',
            $unique,
        ];

        if ($isVip) {
            $rules[] = Rule::notIn([Customer::NON_VIP_ACCOUNT_NUMBER]);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function accountNumberMessages(): array
    {
        return [
            'account_number.not_in' => 'VIP clients need their own account number. 11001-0000 is for non-VIP customers.',
        ];
    }

    private function requestIsVip(Request $request): bool
    {
        if ($request->exists('is_vip')) {
            return $request->boolean('is_vip');
        }

        return strtolower((string) $request->input('customer_type', '')) === 'vip';
    }

    private function normalizeIdentityInput(Request $request): void
    {
        $merge = [];
        if ($request->exists('account_number')) {
            $merge['account_number'] = trim((string) $request->input('account_number'));
        }
        if ($request->exists('name')) {
            $merge['name'] = trim((string) $request->input('name'));
        }
        if ($merge !== []) {
            $request->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCustomer(Customer $customer, bool $includeTaxDetails = false): array
    {
        $payload = $customer->toArray();
        unset($payload['withholding_certificates'], $payload['tax_exemptions']);

        $withholding = $customer->relationLoaded('withholdingCertificates')
            ? $customer->withholdingCertificates
            : new EloquentCollection;
        $exemptions = $customer->relationLoaded('taxExemptions')
            ? $customer->taxExemptions
            : new EloquentCollection;

        $payload['tax_verification'] = [
            'withholding' => $this->summarizeTaxTrack($withholding, 'withholding'),
            'exemption' => $this->summarizeTaxTrack($exemptions, 'exemption'),
        ];

        if ($includeTaxDetails) {
            $payload['tax_verification']['withholding']['requests'] = $withholding
                ->take(8)
                ->map(fn (CustomerWithholdingCertificate $row) => [
                    'id' => $row->id,
                    'certificate_no' => $row->certificate_no,
                    'status' => $row->status,
                    'period_from' => optional($row->period_from)?->toDateString(),
                    'period_to' => optional($row->period_to)?->toDateString(),
                    'certified_amount' => $row->certified_amount,
                    'private_file_id' => $row->private_file_id,
                    'private_file' => $this->presentTaxEvidencePrivateFile($row->privateFile),
                    'created_at' => optional($row->created_at)?->toIso8601String(),
                    'reviewed_at' => optional($row->reviewed_at)?->toIso8601String(),
                ])
                ->values()
                ->all();

            $payload['tax_verification']['exemption']['requests'] = $exemptions
                ->take(8)
                ->map(fn (CustomerTaxExemption $row) => [
                    'id' => $row->id,
                    'exemption_type' => $row->exemption_type,
                    'ruling_or_cert_no' => $row->ruling_or_cert_no,
                    'status' => $row->status,
                    'valid_from' => optional($row->valid_from)?->toDateString(),
                    'valid_to' => optional($row->valid_to)?->toDateString(),
                    'private_file_id' => $row->private_file_id,
                    'private_file' => $this->presentTaxEvidencePrivateFile($row->privateFile),
                    'created_at' => optional($row->created_at)?->toIso8601String(),
                    'reviewed_at' => optional($row->reviewed_at)?->toIso8601String(),
                ])
                ->values()
                ->all();
        }

        return $payload;
    }

    /**
     * Minimal private-file metadata for Admin preview titles.
     * Binary download stays on GET /api/v1/files/{id}/download.
     *
     * @return array{id: int, latest_version: array{original_name: string|null, mime_type: string|null}|null}|null
     */
    private function presentTaxEvidencePrivateFile(?PrivateFile $file): ?array
    {
        if ($file === null) {
            return null;
        }

        $latest = $file->relationLoaded('latestVersion') ? $file->latestVersion : null;

        return [
            'id' => $file->id,
            'latest_version' => $latest === null ? null : [
                'original_name' => $latest->original_name,
                'mime_type' => $latest->mime_type,
            ],
        ];
    }

    /**
     * @param  EloquentCollection<int, CustomerWithholdingCertificate|CustomerTaxExemption>|Collection<int, mixed>  $rows
     * @return array<string, mixed>
     */
    private function summarizeTaxTrack(EloquentCollection|Collection $rows, string $kind): array
    {
        $latest = $rows->first();
        $actionableStatuses = [
            CustomerWithholdingCertificate::STATUS_PENDING_REVIEW,
            CustomerWithholdingCertificate::STATUS_NEEDS_CORRECTION,
        ];
        $pendingCount = $rows->whereIn('status', $actionableStatuses)->count();
        $approvedCount = $rows->where('status', CustomerWithholdingCertificate::STATUS_APPROVED)->count();

        return [
            'kind' => $kind,
            'latest_status' => $latest?->status,
            'latest_id' => $latest?->id,
            'total' => $rows->count(),
            'pending_count' => $pendingCount,
            'approved_count' => $approvedCount,
            'display_status' => $this->resolveTaxDisplayStatus($rows),
        ];
    }

    /**
     * Prefer actionable review states, then the latest request status.
     *
     * @param  EloquentCollection<int, CustomerWithholdingCertificate|CustomerTaxExemption>|Collection<int, mixed>  $rows
     */
    private function resolveTaxDisplayStatus(EloquentCollection|Collection $rows): ?string
    {
        if ($rows->isEmpty()) {
            return null;
        }

        $priority = [
            CustomerWithholdingCertificate::STATUS_PENDING_REVIEW,
            CustomerWithholdingCertificate::STATUS_NEEDS_CORRECTION,
            CustomerWithholdingCertificate::STATUS_APPROVED,
            CustomerWithholdingCertificate::STATUS_REJECTED,
            CustomerWithholdingCertificate::STATUS_EXPIRED,
            CustomerWithholdingCertificate::STATUS_REVOKED,
        ];

        foreach ($priority as $status) {
            if ($rows->contains(fn ($row) => $row->status === $status)) {
                return $status;
            }
        }

        return $rows->first()?->status;
    }
}
