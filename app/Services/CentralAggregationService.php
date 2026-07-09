<?php

namespace App\Services;

use App\Models\Dashboard;
use App\Models\Mouvement;
use App\Models\RecetteClientPush;
use App\Models\Region;
use App\Support\DashboardKpis;
use App\Support\MandatCounter;
use Illuminate\Support\Collection;

class CentralAggregationService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(
        ?int $annee = null,
        ?int $mois = null,
        ?string $dateDebut = null,
        ?string $dateFin = null,
        ?string $regionCode = null,
    ): array {
        if ($dateDebut !== null) {
            return $this->summaryForDateRange($dateDebut, $dateFin ?? $dateDebut, $regionCode);
        }

        $regions = $this->activeRegions($regionCode);

        $regionRows = [];
        $global = DashboardKpis::empty();

        $regionsWithData = 0;
        $latestUpdate = null;
        $globalMandatsCount = 0;
        $globalRecettesCount = 0;
        $globalMouvementsCount = 0;

        foreach ($regions as $region) {
            $dashboard = $this->resolveDashboard($region, $annee, $mois);
            $counts = [
                'mouvements_count' => 0,
                'mandats_count' => 0,
                'recettes_count' => 0,
            ];

            if ($dashboard) {
                $counts = $this->summaryCounts($this->mouvementsForDashboard($dashboard, $annee, $mois));

                if ($counts['mouvements_count'] > 0) {
                    $regionsWithData++;
                }

                $dashboardKpis = DashboardKpis::fromDashboard($dashboard);
                $global['total_ordonnance'] += $dashboardKpis['total_ordonnance'];
                $global['total_recouvrements_4121'] += $dashboardKpis['total_recouvrements_4121'];
                $global['total_montant_paye'] += $dashboardKpis['total_montant_paye'];
                $global['solde'] += $dashboardKpis['solde'];
                $global['tresorerie_reelle'] += $dashboardKpis['tresorerie_reelle'];
                $globalMandatsCount += $counts['mandats_count'];
                $globalRecettesCount += $counts['recettes_count'];
                $globalMouvementsCount += $counts['mouvements_count'];

                if ($dashboard->updated_at && ($latestUpdate === null || $dashboard->updated_at->gt($latestUpdate))) {
                    $latestUpdate = $dashboard->updated_at;
                }
            }

            $regionRows[] = $this->buildRegionRow($region, $dashboard, $counts);
        }

        return [
            'periode' => [
                'annee' => $annee,
                'mois' => $mois,
                'date_debut' => null,
                'date_fin' => null,
            ],
            'global' => $global,
            'regions' => $regionRows,
            'meta' => [
                'regions_actives' => $regions->count(),
                'regions_avec_donnees' => $regionsWithData,
                'mandats_count' => $globalMandatsCount,
                'recettes_count' => $globalRecettesCount,
                'mouvements_count' => $globalMouvementsCount,
                'derniere_mise_a_jour' => $latestUpdate?->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryForDateRange(string $dateDebut, string $dateFin, ?string $regionCode = null): array
    {
        $regions = $this->activeRegions($regionCode);

        $regionRows = [];
        $global = DashboardKpis::empty();

        $regionsWithData = 0;
        $latestUpdate = null;
        $globalMandatsCount = 0;
        $globalRecettesCount = 0;
        $globalMouvementsCount = 0;

        foreach ($regions as $region) {
            $dashboardIds = Dashboard::query()
                ->where('region_id', $region->id)
                ->pluck('id');

            $mouvements = Mouvement::query()
                ->whereIn('dashboard_id', $dashboardIds)
                ->whereBetween('date_mouvement', [$dateDebut, $dateFin])
                ->get();

            $counts = $this->summaryCounts($mouvements);
            $latestDashboard = Dashboard::query()
                ->where('region_id', $region->id)
                ->orderByDesc('updated_at')
                ->first();

            if ($counts['mouvements_count'] > 0) {
                $financial = MandatCounter::financialTotals($mouvements);
                $recouvrements = $financial['total_recouvrements_4121'] > 0
                    ? $financial['total_recouvrements_4121']
                    : $this->recettesForDateRange($dashboardIds, $dateDebut, $dateFin);
                $regionKpis = DashboardKpis::fromFinancialTotals([
                    'total_ordonnance' => $financial['total_ordonnance'],
                    'total_recouvrements_4121' => $recouvrements,
                    'total_montant_paye' => $financial['total_montant_paye'],
                ], $latestDashboard ? (float) $latestDashboard->tresorerie_reelle : 0.0);

                $regionsWithData++;
                $global['total_ordonnance'] += $regionKpis['total_ordonnance'];
                $global['total_recouvrements_4121'] += $regionKpis['total_recouvrements_4121'];
                $global['total_montant_paye'] += $regionKpis['total_montant_paye'];
                $global['solde'] += $regionKpis['solde'];
                $global['tresorerie_reelle'] += $regionKpis['tresorerie_reelle'];
                $globalMandatsCount += $counts['mandats_count'];
                $globalRecettesCount += $counts['recettes_count'];
                $globalMouvementsCount += $counts['mouvements_count'];

                if ($latestDashboard?->updated_at && ($latestUpdate === null || $latestDashboard->updated_at->gt($latestUpdate))) {
                    $latestUpdate = $latestDashboard->updated_at;
                }

                $regionRows[] = [
                    'region' => [
                        'code' => $region->code,
                        'nom' => $region->nom,
                    ],
                    'kpis' => $regionKpis,
                    'meta' => [
                        'has_data' => true,
                        'mouvements_count' => $counts['mouvements_count'],
                        'mandats_count' => $counts['mandats_count'],
                        'recettes_count' => $counts['recettes_count'],
                        'derniere_mise_a_jour' => $latestDashboard?->updated_at?->toIso8601String(),
                    ],
                ];
            } else {
                $regionRows[] = $this->buildRegionRow($region, null, $counts);
            }
        }

        return [
            'periode' => [
                'annee' => (int) substr($dateDebut, 0, 4),
                'mois' => null,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
            ],
            'global' => $global,
            'regions' => $regionRows,
            'meta' => [
                'regions_actives' => $regions->count(),
                'regions_avec_donnees' => $regionsWithData,
                'mandats_count' => $globalMandatsCount,
                'recettes_count' => $globalRecettesCount,
                'mouvements_count' => $globalMouvementsCount,
                'derniere_mise_a_jour' => $latestUpdate?->toIso8601String(),
            ],
        ];
    }

    /** @return Collection<int, Region> */
    private function activeRegions(?string $regionCode = null): Collection
    {
        $query = Region::query()->actives()->ordered();

        if ($regionCode !== null && $regionCode !== '') {
            $query->where('code', $regionCode);
        }

        return $query->get();
    }

    /** @return array<string, mixed> */
    private function buildRegionRow(Region $region, ?Dashboard $dashboard, array $counts): array
    {
        return [
            'region' => [
                'code' => $region->code,
                'nom' => $region->nom,
            ],
            'kpis' => $dashboard ? DashboardKpis::fromDashboard($dashboard) : DashboardKpis::empty(),
            'meta' => [
                'has_data' => $dashboard !== null,
                'mouvements_count' => $counts['mouvements_count'],
                'mandats_count' => $counts['mandats_count'],
                'recettes_count' => $counts['recettes_count'],
                'derniere_mise_a_jour' => $dashboard?->updated_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * @param  Collection<int, Mouvement>  $mouvements
     * @return array{mouvements_count: int, mandats_count: int, recettes_count: int}
     */
    private function summaryCounts(Collection $mouvements): array
    {
        $rows = MandatCounter::dedupeRows($mouvements);

        return [
            'mouvements_count' => $rows->count(),
            'mandats_count' => MandatCounter::navMandatLines($rows)->count(),
            'recettes_count' => $rows->where('type', 'recette')->count(),
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
            $query->where(function ($q) use ($mois) {
                $q->where('mois', $mois)->orWhereNull('mois');
            });
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

    /**
     * @param  Collection<int, int|string>  $dashboardIds
     */
    private function recettesForDateRange(Collection $dashboardIds, string $dateDebut, string $dateFin): float
    {
        if ($dashboardIds->isEmpty()) {
            return 0.0;
        }

        return (float) RecetteClientPush::query()
            ->whereIn('dashboard_id', $dashboardIds)
            ->where(function ($query) use ($dateDebut, $dateFin) {
                $query->whereBetween('date_posting', [$dateDebut, $dateFin])
                    ->orWhere(function ($fallback) use ($dateDebut) {
                        $fallback->whereNull('date_posting')
                            ->where('exercice', (int) substr($dateDebut, 0, 4));
                    });
            })
            ->get()
            ->unique('regional_id')
            ->sum(fn (RecetteClientPush $r) => (float) $r->montant);
    }
}
