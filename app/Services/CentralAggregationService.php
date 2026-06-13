<?php

namespace App\Services;

use App\Models\Dashboard;
use App\Models\Mouvement;
use App\Models\Region;
use Illuminate\Support\Collection;

class CentralAggregationService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(?int $annee = null, ?int $mois = null): array
    {
        $regions = Region::query()->actives()->ordered()->get();

        $regionRows = [];
        $global = [
            'total_recettes' => 0.0,
            'total_depenses' => 0.0,
            'solde' => 0.0,
            'encaisse' => 0.0,
        ];

        $regionsWithData = 0;
        $latestUpdate = null;

        foreach ($regions as $region) {
            $dashboard = $this->resolveDashboard($region, $annee, $mois);
            $mouvementsCount = 0;

            if ($dashboard) {
                $mouvementsCount = $this->mouvementsForDashboard($dashboard, $annee, $mois)->count();
                $regionsWithData++;

                $global['total_recettes'] += (float) $dashboard->total_recettes;
                $global['total_depenses'] += (float) $dashboard->total_depenses;
                $global['solde'] += (float) $dashboard->solde;
                $global['encaisse'] += (float) $dashboard->encaisse;

                if ($dashboard->updated_at && ($latestUpdate === null || $dashboard->updated_at->gt($latestUpdate))) {
                    $latestUpdate = $dashboard->updated_at;
                }
            }

            $regionRows[] = [
                'region' => [
                    'code' => $region->code,
                    'nom' => $region->nom,
                ],
                'kpis' => $dashboard ? [
                    'total_recettes' => (float) $dashboard->total_recettes,
                    'total_depenses' => (float) $dashboard->total_depenses,
                    'solde' => (float) $dashboard->solde,
                    'encaisse' => (float) $dashboard->encaisse,
                ] : [
                    'total_recettes' => 0.0,
                    'total_depenses' => 0.0,
                    'solde' => 0.0,
                    'encaisse' => 0.0,
                ],
                'meta' => [
                    'has_data' => $dashboard !== null,
                    'mouvements_count' => $mouvementsCount,
                    'derniere_mise_a_jour' => $dashboard?->updated_at?->toIso8601String(),
                ],
            ];
        }

        return [
            'periode' => [
                'annee' => $annee,
                'mois' => $mois,
            ],
            'global' => $global,
            'regions' => $regionRows,
            'meta' => [
                'regions_actives' => $regions->count(),
                'regions_avec_donnees' => $regionsWithData,
                'derniere_mise_a_jour' => $latestUpdate?->toIso8601String(),
            ],
        ];
    }

    private function resolveDashboard(Region $region, ?int $annee, ?int $mois): ?Dashboard
    {
        $query = Dashboard::query()
            ->where('region_id', $region->id)
            ->orderByDesc('updated_at');

        if ($annee !== null) {
            $query->where('annee', $annee);
        }

        if ($mois !== null) {
            $query->where('mois', $mois);
        }

        return $query->first();
    }

    /** @return Collection<int, Mouvement> */
    private function mouvementsForDashboard(Dashboard $dashboard, ?int $annee, ?int $mois): Collection
    {
        $query = Mouvement::query()->where('dashboard_id', $dashboard->id);

        if ($annee !== null) {
            $query->where('annee', $annee);
        }

        if ($mois !== null) {
            $query->where('mois', $mois);
        }

        return $query->get();
    }
}
