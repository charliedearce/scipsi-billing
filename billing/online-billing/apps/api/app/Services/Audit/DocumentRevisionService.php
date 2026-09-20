<?php

namespace App\Services\Audit;

use App\Exceptions\ConcurrencyException;
use App\Models\DocumentRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DocumentRevisionService
{
    protected const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'remember_token',
        'bearer_token',
        'secret',
        'otp',
        'otp_code',
        'api_key',
        'card_number',
        'cvv',
        'pin',
    ];

    /**
     * Create an immutable revision snapshot for a document.
     */
    public function createRevision(
        int $organizationId,
        ?int $locationId,
        string $documentType,
        int $documentId,
        User $actor,
        array $newSnapshot,
        ?string $reason = null,
        int $expectedVersion = 1
    ): DocumentRevision {
        return DB::transaction(function () use (
            $organizationId,
            $locationId,
            $documentType,
            $documentId,
            $actor,
            $newSnapshot,
            $reason,
            $expectedVersion
        ) {
            // Find the most recent revision for this document (with pessimistic lock to serialize concurrent revision attempts)
            $latest = DocumentRevision::where('organization_id', $organizationId)
                ->where('document_type', $documentType)
                ->where('document_id', $documentId)
                ->orderBy('revision_number', 'desc')
                ->lockForUpdate()
                ->first();

            $redactedSnapshot = $this->redactSensitiveFields($newSnapshot);

            if (! $latest) {
                if ($expectedVersion !== 1) {
                    throw new ConcurrencyException("Initial document revision requires expected_version 1, but {$expectedVersion} was provided.");
                }
                $revisionNumber = 1;
                $lockVersion = 1;
                $changedFields = array_keys($redactedSnapshot);
            } else {
                if ($latest->lock_version !== $expectedVersion) {
                    throw new ConcurrencyException("Document edit conflict: current version is {$latest->lock_version}, expected {$expectedVersion}.");
                }
                $revisionNumber = $latest->revision_number + 1;
                $lockVersion = $expectedVersion + 1;
                $changedFields = $this->calculateChangedFields($latest->snapshot ?? [], $redactedSnapshot);
            }

            $canonicalJson = $this->canonicalJsonEncode($redactedSnapshot);
            $snapshotHash = hash('sha256', $canonicalJson);

            return DocumentRevision::create([
                'organization_id' => $organizationId,
                'location_id' => $locationId,
                'document_type' => $documentType,
                'document_id' => $documentId,
                'revision_number' => $revisionNumber,
                'actor_type' => get_class($actor),
                'actor_id' => $actor->id,
                'reason' => $reason,
                'changed_fields' => $changedFields,
                'snapshot' => $redactedSnapshot,
                'snapshot_hash' => $snapshotHash,
                'lock_version' => $lockVersion,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Compare two revisions and return structured field-level diff.
     */
    public function compareRevisions(int $revisionAId, int $revisionBId): array
    {
        $revA = DocumentRevision::with('actor')->findOrFail($revisionAId);
        $revB = DocumentRevision::with('actor')->findOrFail($revisionBId);

        $snapA = $revA->snapshot ?? [];
        $snapB = $revB->snapshot ?? [];

        $diff = [];
        $allKeys = array_unique(array_merge(array_keys($snapA), array_keys($snapB)));
        sort($allKeys);

        foreach ($allKeys as $key) {
            $valA = $snapA[$key] ?? null;
            $valB = $snapB[$key] ?? null;

            if ($valA !== $valB) {
                $diff[$key] = [
                    'old' => $valA,
                    'new' => $valB,
                ];
            }
        }

        return [
            'revision_a' => [
                'id' => $revA->id,
                'revision_number' => $revA->revision_number,
                'lock_version' => $revA->lock_version,
                'created_at' => $revA->created_at->toIso8601String(),
                'actor' => $revA->actor ? [
                    'id' => $revA->actor->id,
                    'name' => $revA->actor->name,
                    'email' => $revA->actor->email,
                ] : null,
                'reason' => $revA->reason,
                'snapshot_hash' => $revA->snapshot_hash,
            ],
            'revision_b' => [
                'id' => $revB->id,
                'revision_number' => $revB->revision_number,
                'lock_version' => $revB->lock_version,
                'created_at' => $revB->created_at->toIso8601String(),
                'actor' => $revB->actor ? [
                    'id' => $revB->actor->id,
                    'name' => $revB->actor->name,
                    'email' => $revB->actor->email,
                ] : null,
                'reason' => $revB->reason,
                'snapshot_hash' => $revB->snapshot_hash,
            ],
            'diff' => $diff,
            'changed_field_count' => count($diff),
        ];
    }

    /**
     * Verify whether a revision's SHA-256 hash matches its stored canonical JSON.
     */
    public function verifyIntegrity(DocumentRevision $revision): bool
    {
        $canonicalJson = $this->canonicalJsonEncode($revision->snapshot ?? []);

        return hash_equals($revision->snapshot_hash, hash('sha256', $canonicalJson));
    }

    /**
     * Compute array of field names that changed between old and new snapshots.
     */
    protected function calculateChangedFields(array $old, array $new): array
    {
        $changed = [];
        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($allKeys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;

            if ($oldVal !== $newVal) {
                $changed[] = $key;
            }
        }

        sort($changed);

        return $changed;
    }

    /**
     * Scrub sensitive fields recursively.
     */
    public function redactSensitiveFields(array $data): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $redacted[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $redacted[$key] = $this->redactSensitiveFields($value);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    /**
     * Canonicalize JSON encoding by sorting keys recursively for deterministic SHA-256 hashing.
     */
    protected function canonicalJsonEncode(array $data): string
    {
        $sorted = $this->sortKeysRecursively($data);

        return (string) json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function sortKeysRecursively(array $data): array
    {
        $isAssoc = array_keys($data) !== range(0, count($data) - 1) && count($data) > 0;

        if ($isAssoc) {
            ksort($data);
        }

        foreach ($data as $key => $val) {
            if (is_array($val)) {
                $data[$key] = $this->sortKeysRecursively($val);
            }
        }

        return $data;
    }
}
