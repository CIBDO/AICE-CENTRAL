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
            'data' => $this->service->kpis(
                $validated['annee'] ?? null,
                $validated['mois'] ?? null,
                $validated['date_debut'] ?? null,
                $validated['date_fin'] ?? null,
                $validated['region_code'] ?? null,
                $validated['compare_mode'] ?? null,
                $validated['sla_warning_days'] ?? null,
                $validated['sla_critical_days'] ?? null,
            ),
        ]);
    }

    public function alertes(Request $request)
    {
        $validated = $this->validatedPeriod($request);

        return response()->json([
            'status' => 'OK',
            'data' => $this->service->alertes(
                $validated['annee'] ?? null,
                $validated['mois'] ?? null,
                $validated['date_debut'] ?? null,
                $validated['date_fin'] ?? null,
                $validated['region_code'] ?? null,
                $validated['compare_mode'] ?? null,
                $validated['sla_warning_days'] ?? null,
                $validated['sla_critical_days'] ?? null,
            ),
        ]);
    }

    public function anomalies(Request $request)
    {
        $validated = $this->validatedPeriod($request);

        return response()->json([
            'status' => 'OK',
            'data' => $this->service->anomalies(
                $validated['annee'] ?? null,
                $validated['mois'] ?? null,
                $validated['date_debut'] ?? null,
                $validated['date_fin'] ?? null,
                $validated['region_code'] ?? null,
                $validated['compare_mode'] ?? null,
                $validated['sla_warning_days'] ?? null,
                $validated['sla_critical_days'] ?? null,
            ),
        ]);
    }

    public function predictions(Request $request)
    {
        $validated = $this->validatedPeriod($request);

        return response()->json([
            'status' => 'OK',
            'data' => $this->service->predictions(
                $validated['annee'] ?? null,
                $validated['mois'] ?? null,
                $validated['date_debut'] ?? null,
                $validated['date_fin'] ?? null,
                $validated['region_code'] ?? null,
                $validated['compare_mode'] ?? null,
                $validated['sla_warning_days'] ?? null,
                $validated['sla_critical_days'] ?? null,
            ),
        ]);
    }

    /** @return array<string, int|string|null> */
    private function validatedPeriod(Request $request): array
    {
        return $request->validate([
            'annee' => 'nullable|integer|min:2000|max:2100',
            'mois' => 'nullable|integer|min:1|max:12',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'region_code' => 'nullable|string|max:50',
            'compare_mode' => 'nullable|in:mois_precedent,periode_precedente',
            'sla_warning_days' => 'nullable|integer|min:1|max:365',
            'sla_critical_days' => 'nullable|integer|min:1|max:365',
        ]);
    }
}
