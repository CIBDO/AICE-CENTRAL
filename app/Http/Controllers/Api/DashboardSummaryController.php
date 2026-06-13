<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardQueryService;
use Illuminate\Http\Request;

class DashboardSummaryController extends Controller
{
    public function __construct(private readonly DashboardQueryService $service)
    {
    }

    /**
     * GET /api/v1/dashboards/summary
     */
    public function show(Request $request)
    {
        $validated = $request->validate([
            'region_code' => 'nullable|string|max:50',
            'annee' => 'nullable|integer|min:2000|max:2100',
            'mois' => 'nullable|integer|min:1|max:12',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
        ]);

        $summary = $this->service->summary(
            $validated['region_code'] ?? null,
            $validated['annee'] ?? null,
            isset($validated['mois']) ? (int) $validated['mois'] : null,
            $validated['date_debut'] ?? null,
            $validated['date_fin'] ?? null,
        );

        return response()->json([
            'status' => 'OK',
            'data' => $summary,
        ]);
    }
}
