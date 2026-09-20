<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DocumentTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = DocumentType::where('organization_id', $user->organization_id);

        if ($request->filled('purpose')) {
            $query->where('purpose', $request->input('purpose'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $types = $query->orderBy('name', 'asc')->get();

        return response()->json($types);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $type = DocumentType::where('organization_id', $user->organization_id)->findOrFail($id);

        return response()->json($type);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:80',
                Rule::unique('document_types', 'code')->where('organization_id', $user->organization_id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'purpose' => ['required', 'string', Rule::in([
                'BILLING_SUPPORT',
                'WITHHOLDING_CERTIFICATE',
                'EXEMPTION_EVIDENCE',
                'PAYMENT_PROOF',
            ])],
            'allowed_mime_types' => ['nullable', 'array'],
            'allowed_mime_types.*' => ['string'],
            'max_file_size_kb' => ['nullable', 'integer', 'min:100', 'max:25600'],
            'max_files' => ['nullable', 'integer', 'min:1', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['organization_id'] = $user->organization_id;
        $docType = DocumentType::create($validated);

        return response()->json($docType, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $docType = DocumentType::where('organization_id', $user->organization_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'allowed_mime_types' => ['nullable', 'array'],
            'max_file_size_kb' => ['nullable', 'integer', 'min:100', 'max:25600'],
            'max_files' => ['nullable', 'integer', 'min:1', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $docType->update($validated);

        return response()->json($docType);
    }
}
