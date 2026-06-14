<?php

namespace App\Services;

use App\Models\Mouvement;
use App\Models\Region;
use Illuminate\Support\Collection;

class RegionalSyncService
{
    /**
     * Liste des regional_id de mouvements déjà stockés pour une région et une période.
     *
     * @return array{regional_ids: array<int, string>, count: int}
     */
    public function existingMouvementRegionalIds(Region $region, int $periode): array
    {
        $regionalIds = Mouvement::query()
            ->select('mouvements.regional_id')
            ->join('dashboards', 'dashboards.id', '=', 'mouvements.dashboard_id')
            ->where('dashboards.region_id', $region->id)
            ->where('dashboards.annee', $periode)
            ->whereNotNull('mouvements.regional_id')
            ->where('mouvements.regional_id', '!=', '')
            ->distinct()
            ->orderBy('mouvements.regional_id')
            ->pluck('mouvements.regional_id');

        /** @var Collection<int, string> $unique */
        $unique = $regionalIds->values();

        return [
            'regional_ids' => $unique->all(),
            'count' => $unique->count(),
        ];
    }
}
