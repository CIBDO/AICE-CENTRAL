<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PushObservabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushEventController extends Controller
{
    public function __construct(private readonly PushObservabilityService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $this->filters($request);
        $paginator = $this->service->paginateEvents($validated);

        return response()->json([
            'status' => 'OK',
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function regions(Request $request): JsonResponse
    {
        $validated = $this->filters($request);

        return response()->json([
            'status' => 'OK',
            'data' => [
                'summary' => $this->service->summary($validated),
                'regions' => $this->service->regionsOverview($validated),
                'top_errors' => $this->service->topErrors($validated),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'region_code' => 'nullable|string|max:50',
            'status' => 'nullable|in:OK,ERROR',
            'endpoint' => 'nullable|string|max:255',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
            'retard_minutes' => 'nullable|integer|min:1|max:10080',
        ]);
    }
}
