<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CentralAggregationService;
use Illuminate\Http\Request;

class CentralSummaryController extends Controller
{
    public function __construct(private readonly CentralAggregationService $service)
    {
    }

    public function show(Request $request)
    {
        $validated = $request->validate([
            'annee' => 'nullable|integer|min:2000|max:2100',
            'mois' => 'nullable|integer|min:1|max:12',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'region_code' => 'nullable|string|max:50',
        ]);

        $summary = $this->service->summary(
            $validated['annee'] ?? null,
            isset($validated['mois']) ? (int) $validated['mois'] : null,
            $validated['date_debut'] ?? null,
            $validated['date_fin'] ?? null,
            $validated['region_code'] ?? null,
        );

        return response()->json([
            'status' => 'OK',
            'data' => $summary,
        ]);
    }
}
