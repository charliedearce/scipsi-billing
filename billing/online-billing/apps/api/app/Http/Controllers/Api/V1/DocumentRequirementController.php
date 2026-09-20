<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ConcurrencyException;
use App\Http\Controllers\Controller;
use App\Models\DocumentRequirement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentRequirementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = DocumentRequirement::where('organization_id', $user->organization_id)
            ->with('documentType');

        if ($request->filled('service_type')) {
            $query->where('service_type', $request->input('service_type'));
        }

        if ($request->filled('location_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('location_id', $request->input('location_id'))
                    ->orWhereNull('location_id');
            });
        }

        $requirements = $query->orderBy('service_type', 'asc')->get();

        return response()->json($requirements);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $requirement = DocumentRequirement::where('organization_id', $user->organization_id)
            ->with('documentType')
            ->findOrFail($id);

        return response()->json($requirement);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'service_type' => ['required', 'string', 'max:80'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'is_required' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $validated['organization_id'] = $user->organization_id;
        $validated['version'] = 1;
        $validated['lock_version'] = 1;

        $requirement = DocumentRequirement::create($validated);

        return response()->json($requirement->load('documentType'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $requirement = DocumentRequirement::where('organization_id', $user->organization_id)->findOrFail($id);

        $validated = $request->validate([
            'is_required' => ['sometimes', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date'],
            'lock_version' => ['required', 'integer'],
        ]);

        if ($requirement->lock_version !== $validated['lock_version']) {
            throw new ConcurrencyException("Document requirement was modified by another user. Current version: {$requirement->lock_version}, provided: {$validated['lock_version']}");
        }

        $requirement->update([
            'is_required' => $validated['is_required'] ?? $requirement->is_required,
            'effective_from' => $validated['effective_from'] ?? $requirement->effective_from,
            'effective_to' => $validated['effective_to'] ?? $requirement->effective_to,
            'version' => $requirement->version + 1,
            'lock_version' => $requirement->lock_version + 1,
        ]);

        return response()->json($requirement->load('documentType'));
    }
}
