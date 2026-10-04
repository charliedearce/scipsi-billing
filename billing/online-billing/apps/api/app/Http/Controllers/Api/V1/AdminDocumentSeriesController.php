<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentNumber;
use App\Models\DocumentSeries;
use App\Models\User;
use App\Services\Audit\AuditEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminDocumentSeriesController extends Controller
{
    public function __construct(protected AuditEventService $auditEvents) {}

    public function index(Request $request): JsonResponse
    {
        $series = DocumentSeries::query()
            ->where('organization_id', $request->user()->organization_id)
            ->with('location:id,name,code')
            ->orderBy('document_type')
            ->orderBy('series_code')
            ->get();

        return response()->json(['data' => $series]);
    }

    public function updatePrefix(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'prefix' => ['present', 'string', 'max:32', 'regex:~\A[A-Za-z0-9._/-]*\z~'],
            'expected_lock_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $actor = $request->user();
        $prefix = strtoupper(trim($validated['prefix']));

        $result = DB::transaction(function () use ($request, $validated, $actor, $id, $prefix): array {
            // Lock the organization's series in a stable order so simultaneous prefix changes
            // cannot both pass the number-range collision check.
            $seriesRows = DocumentSeries::query()
                ->where('organization_id', $actor->organization_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $series = $seriesRows->first(fn (DocumentSeries $candidate): bool => $candidate->id === $id);

            abort_if(! $series, 404);
            abort_if(
                $series->lock_version !== (int) $validated['expected_lock_version'],
                409,
                'This document series changed. Refresh the list and try again.'
            );

            if ($series->prefix === $prefix) {
                return ['series' => $series->load('location'), 'changed' => false];
            }

            [$targetStart, $targetEnd] = $this->nextSequenceRange($series);
            $conflict = $seriesRows->first(function (DocumentSeries $other) use ($series, $prefix, $targetStart, $targetEnd): bool {
                if ($other->id === $series->id || $other->prefix !== $prefix) {
                    return false;
                }

                [$otherStart, $otherEnd] = $this->nextSequenceRange($other);

                return $this->rangesOverlap($targetStart, $targetEnd, $otherStart, $otherEnd);
            });

            if ($conflict) {
                throw ValidationException::withMessages([
                    'prefix' => ["This prefix would overlap the future number range for series [{$conflict->series_code}]. Use a different prefix or non-overlapping series range."],
                ]);
            }

            if ($targetStart !== null && $this->hasFutureNumberCollision($series, $actor, $prefix, $targetStart, $targetEnd)) {
                throw ValidationException::withMessages([
                    'prefix' => ['This prefix would reuse a visible number already assigned to another series. Choose a different prefix or series range.'],
                ]);
            }

            $before = [
                'series_code' => $series->series_code,
                'document_type' => $series->document_type,
                'location_id' => $series->location_id,
                'prefix' => $series->prefix,
                'current_number' => $series->current_number,
                'lock_version' => $series->lock_version,
            ];

            $series->prefix = $prefix;
            $series->lock_version++;
            $series->save();

            $after = [
                'series_code' => $series->series_code,
                'document_type' => $series->document_type,
                'location_id' => $series->location_id,
                'prefix' => $series->prefix,
                'current_number' => $series->current_number,
                'lock_version' => $series->lock_version,
            ];

            $this->auditEvents->recordEvent(
                organizationId: (int) $series->organization_id,
                locationId: $series->location_id ? (int) $series->location_id : null,
                eventType: 'DOCUMENT_SERIES_PREFIX_UPDATED',
                aggregateType: 'DOCUMENT_SERIES',
                aggregateId: (int) $series->id,
                aggregateVersion: (int) $series->lock_version,
                actor: $actor,
                permissionSnapshot: 'document_series:manage',
                reason: $validated['reason'],
                beforeSnapshot: $before,
                afterSnapshot: $after,
                request: $request,
                extraMetadata: ['future_allocations_only' => true],
            );

            return ['series' => $series->load('location'), 'changed' => true];
        });

        return response()->json([
            'data' => $result['series'],
            'message' => $result['changed']
                ? 'Prefix updated for future number allocations. Previously issued numbers remain unchanged.'
                : 'The series already uses this prefix.',
        ]);
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function nextSequenceRange(DocumentSeries $series): array
    {
        $start = max((int) $series->start_number, (int) $series->current_number + 1);
        $end = $series->end_number === null ? null : (int) $series->end_number;

        if ($end !== null && $start > $end) {
            return [null, null];
        }

        return [$start, $end];
    }

    private function rangesOverlap(?int $leftStart, ?int $leftEnd, ?int $rightStart, ?int $rightEnd): bool
    {
        if ($leftStart === null || $rightStart === null) {
            return false;
        }

        return ($leftEnd === null || $rightStart <= $leftEnd)
            && ($rightEnd === null || $leftStart <= $rightEnd);
    }

    private function hasFutureNumberCollision(
        DocumentSeries $series,
        User $actor,
        string $prefix,
        int $start,
        ?int $end,
    ): bool {
        $collision = false;
        $query = DocumentNumber::query()
            ->where('organization_id', $actor->organization_id)
            ->where('series_id', '!=', $series->id)
            ->where('sequence_number', '>=', $start)
            ->orderBy('id');

        if ($end !== null) {
            $query->where('sequence_number', '<=', $end);
        }

        $query->chunkById(500, function ($numbers) use ($series, $prefix, &$collision): bool {
            foreach ($numbers as $number) {
                $formatted = $prefix.str_pad((string) $number->sequence_number, $series->padding_length, '0', STR_PAD_LEFT);
                if ($formatted === $number->formatted_number) {
                    $collision = true;

                    return false;
                }
            }

            return true;
        });

        return $collision;
    }
}
