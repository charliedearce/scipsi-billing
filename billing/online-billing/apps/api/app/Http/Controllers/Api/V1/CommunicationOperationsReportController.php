<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Reporting\CommunicationOperationsReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CommunicationOperationsReportController extends Controller
{
    public function __construct(protected CommunicationOperationsReportService $reports) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        [$dateFrom, $dateTo] = $this->resolveDateRange($data['date_from'] ?? null, $data['date_to'] ?? null);

        return response()->json([
            'data' => $this->reports->dashboard($request->user(), $dateFrom, $dateTo),
        ]);
    }

    /** @return array{0:Carbon,1:Carbon} */
    private function resolveDateRange(?string $dateFrom, ?string $dateTo): array
    {
        $today = Carbon::now(CommunicationOperationsReportService::TIMEZONE)->startOfDay();
        $from = $dateFrom === null
            ? $today->copy()->subDays(29)
            : Carbon::createFromFormat('Y-m-d', $dateFrom, CommunicationOperationsReportService::TIMEZONE)->startOfDay();
        $to = $dateTo === null
            ? $today
            : Carbon::createFromFormat('Y-m-d', $dateTo, CommunicationOperationsReportService::TIMEZONE)->startOfDay();

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'date_to' => ['The end date must be on or after the start date.'],
            ]);
        }

        if ($from->diffInDays($to) > 92) {
            throw ValidationException::withMessages([
                'date_to' => ['The communications operations report allows at most 93 calendar days.'],
            ]);
        }

        return [$from, $to];
    }
}
