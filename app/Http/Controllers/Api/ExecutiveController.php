<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExecutiveAnalyticsService;
use Illuminate\Http\Request;

class ExecutiveController extends Controller
{
    public function __construct(private readonly ExecutiveAnalyticsService $service)
    {
    }

    public function kpis(Request $request)
    {
        $validated = $this->validatedPeriod($request);

        return response()->json([
            'status' => 'OK',
            'data' => $this->service->kpis($validated['annee'] ?? null, $validated['mois'] ?? null),
        ]);
    }

    public function alertes(Request $request)
    {
        $validated = $this->validatedPeriod($request);

        return response()->json([
            'status' => 'OK',
            'data' => $this->service->alertes($validated['annee'] ?? null, $validated['mois'] ?? null),
        ]);
    }

    public function anomalies(Request $request)
    {
        $validated = $this->validatedPeriod($request);

        return response()->json([
            'status' => 'OK',
            'data' => $this->service->anomalies($validated['annee'] ?? null, $validated['mois'] ?? null),
        ]);
    }

    public function predictions(Request $request)
    {
        $validated = $this->validatedPeriod($request);

        return response()->json([
            'status' => 'OK',
            'data' => $this->service->predictions($validated['annee'] ?? null, $validated['mois'] ?? null),
        ]);
    }

    /** @return array<string, int|null> */
    private function validatedPeriod(Request $request): array
    {
        return $request->validate([
            'annee' => 'nullable|integer|min:2000|max:2100',
            'mois' => 'nullable|integer|min:1|max:12',
        ]);
    }
}
