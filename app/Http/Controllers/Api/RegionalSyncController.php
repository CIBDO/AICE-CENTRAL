<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Services\RegionalSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegionalSyncController extends Controller
{
    public function __construct(
        private readonly RegionalSyncService $syncService,
    ) {
    }

    /**
     * GET /api/v1/regions/{regionCode}/mouvements/existing-ids?periode=2026
     *
     * Retourne les regional_id déjà enregistrés côté central (mode delta régional).
     */
    public function existingIds(Request $request, string $regionCode): JsonResponse
    {
        $region = $request->attributes->get('authenticated_region');

        if (!$region instanceof Region) {
            return response()->json([
                'status' => 'error',
                'message' => 'Région non authentifiée',
            ], 401);
        }

        if ($this->normalizeRegionCode($regionCode) !== $this->normalizeRegionCode($region->code)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Accès refusé : vous ne pouvez consulter que les données de votre région (' . $region->code . ').',
            ], 403);
        }

        $validated = $request->validate([
            'periode' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $periode = (int) $validated['periode'];
        $result = $this->syncService->existingMouvementRegionalIds($region, $periode);

        return response()->json([
            'status' => 'OK',
            'region_code' => $region->code,
            'periode' => $periode,
            'count' => $result['count'],
            'regional_ids' => $result['regional_ids'],
        ]);
    }

    private function normalizeRegionCode(?string $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }
}
