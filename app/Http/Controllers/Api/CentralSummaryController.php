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
        ]);

        $summary = $this->service->summary(
            $validated['annee'] ?? null,
            isset($validated['mois']) ? (int) $validated['mois'] : null,
        );

        return response()->json([
            'status' => 'OK',
            'data' => $summary,
        ]);
    }
}
