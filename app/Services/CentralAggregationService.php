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
    public function __construct(private readonly BanqueQueryService $banqueQueryService)
    {
    }

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
        $workflow = $this->emptyWorkflow();

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
            $regionWorkflow = $this->emptyWorkflow();

            if ($dashboard) {
                $mouvements = $this->mouvementsForDashboard($dashboard, $annee, $mois);
                $counts = $this->summaryCounts($mouvements);
                $regionWorkflow = MandatCounter::workflowBacklog($mouvements);
                $bankBalance = $this->bankBalance([
                    'region_code' => $region->code,
                    'annee' => $annee,
                    'mois' => $mois,
                ]);

                if ($counts['mouvements_count'] > 0 || $bankBalance !== 0.0) {
                    $regionsWithData++;
                }

                $dashboardKpis = $this->withBankBalance(DashboardKpis::fromDashboard($dashboard), $bankBalance);
                $global['total_ordonnance'] += $dashboardKpis['total_ordonnance'];
                $global['total_recouvrements_4121'] += $dashboardKpis['total_recouvrements_4121'];
                $global['total_montant_paye'] += $dashboardKpis['total_montant_paye'];
                $global['solde'] += $dashboardKpis['solde'];
                $global['tresorerie_reelle'] += $dashboardKpis['tresorerie_reelle'];
                $workflow = $this->mergeWorkflow($workflow, $regionWorkflow);
                $globalMandatsCount += $counts['mandats_count'];
                $globalRecettesCount += $counts['recettes_count'];
                $globalMouvementsCount += $counts['mouvements_count'];

                if ($dashboard->updated_at && ($latestUpdate === null || $dashboard->updated_at->gt($latestUpdate))) {
                    $latestUpdate = $dashboard->updated_at;
                }
            }

            $regionRows[] = $this->buildRegionRow(
                $region,
                $dashboard,
                $counts,
                $regionWorkflow,
                $this->bankBalance([
                    'region_code' => $region->code,
                    'annee' => $annee,
                    'mois' => $mois,
                ]),
            );
        }

        return [
            'periode' => [
                'annee' => $annee,
                'mois' => $mois,
                'date_debut' => null,
                'date_fin' => null,
            ],
            'global' => $global,
            'workflow' => $workflow,
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
        $workflow = $this->emptyWorkflow();

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
            $bankBalance = $this->bankBalance([
                'region_code' => $region->code,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
            ]);

            if ($counts['mouvements_count'] > 0 || $bankBalance !== 0.0) {
                $financial = MandatCounter::financialTotals($mouvements);
                $recouvrements = $financial['total_recouvrements_4121'] > 0
                    ? $financial['total_recouvrements_4121']
                    : $this->recettesForDateRange($dashboardIds, $dateDebut, $dateFin);
                $regionKpis = DashboardKpis::fromFinancialTotals([
                    'total_ordonnance' => $financial['total_ordonnance'],
                    'total_recouvrements_4121' => $recouvrements,
                    'total_montant_paye' => $financial['total_montant_paye'],
                ], $bankBalance);

                $regionsWithData++;
                $global['total_ordonnance'] += $regionKpis['total_ordonnance'];
                $global['total_recouvrements_4121'] += $regionKpis['total_recouvrements_4121'];
                $global['total_montant_paye'] += $regionKpis['total_montant_paye'];
                $global['solde'] += $regionKpis['solde'];
                $global['tresorerie_reelle'] += $regionKpis['tresorerie_reelle'];
                $regionWorkflow = MandatCounter::workflowBacklog($mouvements);
                $workflow = $this->mergeWorkflow($workflow, $regionWorkflow);
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
                    'workflow' => $regionWorkflow,
                    'meta' => [
                        'has_data' => true,
                        'mouvements_count' => $counts['mouvements_count'],
                        'mandats_count' => $counts['mandats_count'],
                        'recettes_count' => $counts['recettes_count'],
                        'derniere_mise_a_jour' => $latestDashboard?->updated_at?->toIso8601String(),
                    ],
                ];
            } else {
                $regionRows[] = $this->buildRegionRow($region, null, $counts, $this->emptyWorkflow(), $bankBalance);
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
            'workflow' => $workflow,
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
    private function buildRegionRow(Region $region, ?Dashboard $dashboard, array $counts, array $workflow, float $bankBalance): array
    {
        return [
            'region' => [
                'code' => $region->code,
                'nom' => $region->nom,
            ],
            'kpis' => $dashboard
                ? $this->withBankBalance(DashboardKpis::fromDashboard($dashboard), $bankBalance)
                : $this->withBankBalance(DashboardKpis::empty(), $bankBalance),
            'workflow' => $workflow,
            'meta' => [
                'has_data' => $dashboard !== null || $bankBalance !== 0.0,
                'mouvements_count' => $counts['mouvements_count'],
                'mandats_count' => $counts['mandats_count'],
                'recettes_count' => $counts['recettes_count'],
                'derniere_mise_a_jour' => $dashboard?->updated_at?->toIso8601String(),
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    private function bankBalance(array $filters): float
    {
        return $this->banqueQueryService->filteredBalance(array_filter(
            $filters,
            fn (mixed $value) => $value !== null && $value !== ''
        ));
    }

    /** @param array<string, float> $kpis
     *  @return array<string, float>
     */
    private function withBankBalance(array $kpis, float $bankBalance): array
    {
        $kpis['tresorerie_reelle'] = $bankBalance;

        return $kpis;
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

    /**
     * @return array{
     *     admis: array{count: int, montant: float},
     *     autres_non_payes: array{count: int, montant: float},
     *     total_hors_rejet: array{count: int, montant: float}
     * }
     */
    private function emptyWorkflow(): array
    {
        return [
            'admis' => ['count' => 0, 'montant' => 0.0],
            'autres_non_payes' => ['count' => 0, 'montant' => 0.0],
            'total_hors_rejet' => ['count' => 0, 'montant' => 0.0],
        ];
    }

    /**
     * @param  array{
     *     admis: array{count: int, montant: float},
     *     autres_non_payes: array{count: int, montant: float},
     *     total_hors_rejet: array{count: int, montant: float}
     * }  $base
     * @param  array{
     *     admis: array{count: int, montant: float},
     *     autres_non_payes: array{count: int, montant: float},
     *     total_hors_rejet: array{count: int, montant: float}
     * }  $incoming
     * @return array{
     *     admis: array{count: int, montant: float},
     *     autres_non_payes: array{count: int, montant: float},
     *     total_hors_rejet: array{count: int, montant: float}
     * }
     */
    private function mergeWorkflow(array $base, array $incoming): array
    {
        foreach (['admis', 'autres_non_payes', 'total_hors_rejet'] as $key) {
            $base[$key]['count'] += (int) ($incoming[$key]['count'] ?? 0);
            $base[$key]['montant'] += (float) ($incoming[$key]['montant'] ?? 0.0);
        }

        return $base;
    }
}
