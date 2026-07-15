<?php

namespace App\Services;

use App\Models\Dashboard;
use App\Models\Mouvement;
use App\Models\RecetteClientPush;
use App\Models\Region;
use App\Support\DashboardKpis;
use App\Support\MandatCounter;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardQueryService
{
    public function __construct(private readonly BanqueQueryService $banqueQueryService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(
        ?string $regionCode,
        ?int $annee = null,
        ?int $mois = null,
        ?string $dateDebut = null,
        ?string $dateFin = null,
    ): array {
        $region = $this->resolveRegion($regionCode);

        if ($dateDebut !== null) {
            return $this->summaryForDateRange($region, $dateDebut, $dateFin ?? $dateDebut);
        }

        $dashboard = $this->resolveDashboard($region, $annee, $mois);

        if (!$dashboard) {
            return $this->emptySummary($region);
        }

        $mouvements = $this->mouvementsForDashboard($dashboard, $annee, $mois);

        return $this->buildSummary(
            $region,
            $dashboard,
            $mouvements,
            [
                'annee' => $annee ?? $dashboard->annee,
                'mois' => $mois ?? $dashboard->mois,
                'date_debut' => optional($dashboard->date_debut)?->toDateString(),
                'date_fin' => optional($dashboard->date_fin)?->toDateString(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryForDateRange(Region $region, string $dateDebut, string $dateFin): array
    {
        $dashboardIds = Dashboard::query()
            ->where('region_id', $region->id)
            ->pluck('id');
        $bankBalance = $this->bankBalance([
            'region_code' => $region->code,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
        ]);
        $bankOverview = $this->bankOverview([
            'region_code' => $region->code,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
        ]);

        $annee = (int) substr($dateDebut, 0, 4);
        $isFullYear = $dateDebut === "{$annee}-01-01" && $dateFin === "{$annee}-12-31";

        // Aligné écran NAV : filtre « Année » = colonne annee (MP_ANNEE), pas seulement date_emission.
        $mouvements = Mouvement::query()
            ->whereIn('dashboard_id', $dashboardIds)
            ->when(
                $isFullYear,
                fn ($query) => $query->where('annee', $annee),
                fn ($query) => $query->where(function ($inner) use ($dateDebut, $dateFin, $annee) {
                    $inner->whereBetween('date_mouvement', [$dateDebut, $dateFin])
                        ->orWhere(function ($fallback) use ($annee) {
                            $fallback->where('annee', $annee)->whereNull('date_mouvement');
                        });
                })
            )
            ->get();

        if ($mouvements->isEmpty()) {
            return $this->emptySummary($region, $dateDebut, $dateFin, $bankBalance, $bankOverview);
        }

        $financial = MandatCounter::financialTotals($mouvements);
        $recouvrements = $financial['total_recouvrements_4121'] > 0
            ? $financial['total_recouvrements_4121']
            : $this->recettesForDateRange($dashboardIds, $dateDebut, $dateFin);

        $latestDashboard = Dashboard::query()
            ->where('region_id', $region->id)
            ->orderByDesc('updated_at')
            ->first();

        $kpis = DashboardKpis::fromFinancialTotals([
            'total_ordonnance' => $financial['total_ordonnance'],
            'total_recouvrements_4121' => $recouvrements,
            'total_montant_paye' => $financial['total_montant_paye'],
        ], $bankBalance);

        return [
            'region' => [
                'code' => $region->code,
                'nom' => $region->nom,
            ],
            'periode' => [
                'annee' => (int) substr($dateDebut, 0, 4),
                'mois' => null,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
            ],
            'kpis' => $kpis,
            'workflow' => MandatCounter::workflowBacklog($mouvements),
            'workflow_insights' => MandatCounter::workflowInsights($mouvements, $dateFin),
            'banques' => $bankOverview,
            'mandats_par_type' => $this->mandatsParType($mouvements),
            'statuts_mandats' => $this->statutsMandats($mouvements),
            'meta' => $this->summaryMeta(
                $mouvements,
                $latestDashboard?->id,
                $latestDashboard?->regional_id,
                $latestDashboard?->updated_at?->toIso8601String(),
            ),
        ];
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
     * @param  array{annee: int|null, mois: int|null, date_debut: string|null, date_fin: string|null}  $periode
     * @return array<string, mixed>
     */
    private function buildSummary(Region $region, Dashboard $dashboard, Collection $mouvements, array $periode): array
    {
        $bankBalance = $this->bankBalance([
            'region_code' => $region->code,
            'annee' => $periode['annee'],
            'mois' => $periode['mois'],
            'date_debut' => $periode['date_debut'],
            'date_fin' => $periode['date_fin'],
        ]);
        $bankOverview = $this->bankOverview([
            'region_code' => $region->code,
            'annee' => $periode['annee'],
            'mois' => $periode['mois'],
            'date_debut' => $periode['date_debut'],
            'date_fin' => $periode['date_fin'],
        ]);
        $financial = MandatCounter::financialTotals($mouvements);
        $recouvrements = $financial['total_recouvrements_4121'] > 0
            ? $financial['total_recouvrements_4121']
            : (float) $dashboard->total_recouvrements_4121;

        $kpis = DashboardKpis::fromFinancialTotals([
            'total_ordonnance' => $financial['total_ordonnance'],
            'total_recouvrements_4121' => $recouvrements,
            'total_montant_paye' => $financial['total_montant_paye'],
        ], $bankBalance);

        return [
            'region' => [
                'code' => $region->code,
                'nom' => $region->nom,
            ],
            'periode' => $periode,
            'kpis' => $kpis,
            'workflow' => MandatCounter::workflowBacklog($mouvements),
            'workflow_insights' => MandatCounter::workflowInsights(
                $mouvements,
                $this->referenceDateForPeriod($periode['annee'], $periode['mois'], $periode['date_fin']),
            ),
            'banques' => $bankOverview,
            'mandats_par_type' => $this->mandatsParType($mouvements),
            'statuts_mandats' => $this->statutsMandats($mouvements),
            'meta' => $this->summaryMeta(
                $mouvements,
                $dashboard->id,
                $dashboard->regional_id,
                $dashboard->updated_at?->toIso8601String(),
            ),
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
            $query->where(function ($q) use ($mois) {
                $q->where('mois', $mois)->orWhereNull('mois');
            });
        }

        $dashboard = $query->first();

        if (!$dashboard || $mois === null || $dashboard->mois !== null) {
            return $dashboard;
        }

        $mouvementsCount = Mouvement::query()
            ->where('dashboard_id', $dashboard->id)
            ->where('mois', $mois)
            ->when($annee !== null, fn ($q) => $q->where('annee', $annee))
            ->count();

        return $mouvementsCount > 0 ? $dashboard : null;
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
        return MandatCounter::parType($mouvements);
    }

    /** @param Collection<int, Mouvement> $mouvements */
    private function statutsMandats(Collection $mouvements): array
    {
        return MandatCounter::parStatut($mouvements);
    }

    /** @return array<string, mixed> */
    private function emptySummary(
        Region $region,
        ?string $dateDebut = null,
        ?string $dateFin = null,
        float $bankBalance = 0.0,
        ?array $bankOverview = null,
    ): array {
        $kpis = DashboardKpis::empty();
        $kpis['tresorerie_reelle'] = $bankBalance;

        return [
            'region' => [
                'code' => $region->code,
                'nom' => $region->nom,
            ],
            'periode' => [
                'annee' => $dateDebut ? (int) substr($dateDebut, 0, 4) : null,
                'mois' => null,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
            ],
            'kpis' => $kpis,
            'workflow' => [
                'admis' => ['count' => 0, 'montant' => 0.0],
                'autres_non_payes' => ['count' => 0, 'montant' => 0.0],
                'total_hors_rejet' => ['count' => 0, 'montant' => 0.0],
            ],
            'workflow_insights' => [
                'temps_par_statut' => [],
                'conversions' => [],
                'reprise_rejets' => ['rejetes_count' => 0, 'repris_count' => 0, 'taux_pct' => 0.0],
                'immobilises_par_statut' => [],
                'aging_admis' => [
                    'count' => 0,
                    'montant' => 0.0,
                    'average_days' => 0.0,
                    'max_days' => 0,
                    'buckets' => [],
                ],
            ],
            'banques' => $bankOverview ?? [
                'pont_tresorerie' => [
                    'solde_debut' => 0.0,
                    'encaissements' => 0.0,
                    'decaissements' => 0.0,
                    'solde_fin' => 0.0,
                ],
                'evolution' => [],
                'top_variations' => [],
                'anomalies' => [],
                'confiance' => [
                    'derniere_date_mouvement' => null,
                    'comptes_inclus' => 0,
                    'lignes_incluses' => 0,
                    'lignes_exclues' => 0,
                ],
            ],
            'mandats_par_type' => [],
            'statuts_mandats' => [],
            'meta' => [
                'dashboard_id' => null,
                'regional_id' => null,
                'derniere_mise_a_jour' => null,
                'mouvements_count' => 0,
                'mandats_count' => 0,
                'recettes_count' => 0,
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

    /** @param array<string, mixed> $filters */
    private function bankOverview(array $filters): array
    {
        return $this->banqueQueryService->overview(array_filter(
            $filters,
            fn (mixed $value) => $value !== null && $value !== ''
        ));
    }

    /**
     * @return array{dashboard_id: int|null, regional_id: string|null, derniere_mise_a_jour: string|null, mouvements_count: int, mandats_count: int, recettes_count: int}
     */
    private function summaryMeta(
        Collection $mouvements,
        ?int $dashboardId,
        ?string $regionalId,
        ?string $updatedAt,
    ): array {
        $rows = MandatCounter::dedupeRows($mouvements);

        return [
            'dashboard_id' => $dashboardId,
            'regional_id' => $regionalId,
            'derniere_mise_a_jour' => $updatedAt,
            'mouvements_count' => $rows->count(),
            'mandats_count' => MandatCounter::navMandatLines($rows)->count(),
            'recettes_count' => $rows->where('type', 'recette')->count(),
        ];
    }

    private function referenceDateForPeriod(?int $annee, ?int $mois, ?string $dateFin): string
    {
        if ($dateFin !== null) {
            return $dateFin;
        }

        if ($annee === null) {
            return now()->toDateString();
        }

        return Carbon::create($annee, $mois ?: 12, 1)
            ->endOfMonth()
            ->toDateString();
    }
}
