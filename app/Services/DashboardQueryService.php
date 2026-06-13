<?php

namespace App\Services;

use App\Models\Dashboard;
use App\Models\Mouvement;
use App\Models\Region;
use Illuminate\Support\Collection;

class DashboardQueryService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(?string $regionCode, ?int $annee = null, ?int $mois = null): array
    {
        $region = $this->resolveRegion($regionCode);
        $dashboard = $this->resolveDashboard($region, $annee, $mois);

        if (!$dashboard) {
            return $this->emptySummary($region);
        }

        $mouvements = $this->mouvementsForDashboard($dashboard, $annee, $mois);

        return [
            'region' => [
                'code' => $region->code,
                'nom' => $region->nom,
            ],
            'periode' => [
                'annee' => $annee ?? $dashboard->annee,
                'mois' => $mois ?? $dashboard->mois,
                'date_debut' => optional($dashboard->date_debut)?->toDateString(),
                'date_fin' => optional($dashboard->date_fin)?->toDateString(),
            ],
            'kpis' => [
                'total_recettes' => (float) $dashboard->total_recettes,
                'total_depenses' => (float) $dashboard->total_depenses,
                'solde' => (float) $dashboard->solde,
                'encaisse' => (float) $dashboard->encaisse,
            ],
            'mandats_par_type' => $this->mandatsParType($mouvements),
            'statuts_mandats' => $this->statutsMandats($mouvements),
            'meta' => [
                'dashboard_id' => $dashboard->id,
                'regional_id' => $dashboard->regional_id,
                'derniere_mise_a_jour' => $dashboard->updated_at?->toIso8601String(),
                'mouvements_count' => $mouvements->count(),
            ],
        ];
    }

    private function resolveRegion(?string $regionCode): Region
    {
        if ($regionCode) {
            $region = Region::query()
                ->where('code', $regionCode)
                ->where('actif', true)
                ->first();

            if ($region) {
                return $region;
            }
        }

        $fallback = Region::query()->actives()->ordered()->first();

        if (!$fallback) {
            abort(404, 'Aucune région active configurée.');
        }

        return $fallback;
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

    /** @param Collection<int, Mouvement> $mouvements */
    private function mandatsParType(Collection $mouvements): array
    {
        $labels = [
            '0' => 'Matériel',
            '1' => 'Salaire',
            '2' => 'Reversement',
        ];

        $depenses = $mouvements->where('type', 'depense');

        $result = [];
        foreach ($labels as $code => $label) {
            $subset = $depenses->where('type_mandat', $code);
            $result[] = [
                'code' => $code,
                'libelle' => $label,
                'count' => $subset->count(),
                'montant' => (float) $subset->sum('montant'),
            ];
        }

        return $result;
    }

    /** @param Collection<int, Mouvement> $mouvements */
    private function statutsMandats(Collection $mouvements): array
    {
        return $mouvements
            ->groupBy(fn (Mouvement $m) => $m->statut ?: 'Non renseigné')
            ->map(fn (Collection $group, string $statut) => [
                'statut' => $statut,
                'count' => $group->count(),
                'montant' => (float) $group->sum('montant'),
            ])
            ->values()
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function emptySummary(Region $region): array
    {
        return [
            'region' => [
                'code' => $region->code,
                'nom' => $region->nom,
            ],
            'periode' => [
                'annee' => null,
                'mois' => null,
                'date_debut' => null,
                'date_fin' => null,
            ],
            'kpis' => [
                'total_recettes' => 0,
                'total_depenses' => 0,
                'solde' => 0,
                'encaisse' => 0,
            ],
            'mandats_par_type' => [],
            'statuts_mandats' => [],
            'meta' => [
                'dashboard_id' => null,
                'regional_id' => null,
                'derniere_mise_a_jour' => null,
                'mouvements_count' => 0,
            ],
        ];
    }
}
