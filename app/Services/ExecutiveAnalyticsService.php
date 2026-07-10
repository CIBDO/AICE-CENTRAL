<?php

namespace App\Services;

use App\Models\Dashboard;
use App\Models\Mouvement;
use App\Models\Region;
use App\Support\MandatCounter;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ExecutiveAnalyticsService
{
    private const SEUIL_REJET_WARNING = 15;

    private const SEUIL_REJET_CRITIQUE = 20;

    private const SEUIL_MANDATS_ATTENTE = 100;

    private const SEUIL_MONTANT_IMPORTANT = 6_000_000;

    /**
     * @return array<string, mixed>
     */
    public function kpis(
        ?int $annee = null,
        ?int $mois = null,
        ?string $dateDebut = null,
        ?string $dateFin = null,
        ?string $regionCode = null,
    ): array {
        if ($dateDebut !== null) {
            $dateFin = $dateFin ?? $dateDebut;
            $mouvements = $this->mouvementsForDateRange($dateDebut, $dateFin, $regionCode);
            $central = app(CentralAggregationService::class)->summary(null, null, $dateDebut, $dateFin, $regionCode);
            $prev = $this->previousDateRange($dateDebut, $dateFin);
            $prevMouvements = $this->mouvementsForDateRange($prev['debut'], $prev['fin'], $regionCode);
            $stats = $this->computeMouvementStats($mouvements);
            $prevStats = $this->computeMouvementStats($prevMouvements);

            return [
                'periode' => [
                    'annee' => (int) substr($dateDebut, 0, 4),
                    'mois' => null,
                    'date_debut' => $dateDebut,
                    'date_fin' => $dateFin,
                ],
                'indicateurs' => [
                    'taux_execution' => $stats['taux_execution'],
                    'taux_rejet' => $stats['taux_rejet'],
                    'mandats_total' => $stats['mandats_total'],
                    'mandats_admis' => $stats['mandats_admis'],
                    'mandats_rejetes' => $stats['mandats_rejetes'],
                    'tresorerie_reelle_total' => $central['global']['tresorerie_reelle'],
                    'recouvrements_4121_total' => $central['global']['total_recouvrements_4121'],
                    'ordonnance_total' => $central['global']['total_ordonnance'],
                    'montant_paye_total' => $central['global']['total_montant_paye'],
                    'solde_total' => $central['global']['solde'],
                ],
                'comparaison_mois_precedent' => [
                    'ordonnance_evolution_pct' => $this->evolutionPercent($stats['ordonnance_montant'], $prevStats['ordonnance_montant']),
                    'recouvrements_evolution_pct' => $this->evolutionPercent($stats['recouvrements_montant'], $prevStats['recouvrements_montant']),
                    'mandats_evolution_pct' => $this->evolutionPercent($stats['mandats_total'], $prevStats['mandats_total']),
                ],
                'performance_regions' => $this->performanceRegionsForDateRange($dateDebut, $dateFin, $regionCode),
                'meta' => $central['meta'],
            ];
        }

        [$annee, $mois] = $this->resolvePeriod($annee, $mois);
        $mouvements = $this->mouvementsForPeriod($annee, $mois, $regionCode);
        $central = app(CentralAggregationService::class)->summary($annee, $mois, null, null, $regionCode);

        $stats = $this->computeMouvementStats($mouvements);
        $prev = $this->previousPeriod($annee, $mois);
        $prevMouvements = $this->mouvementsForPeriod($prev['annee'], $prev['mois'], $regionCode);
        $prevStats = $this->computeMouvementStats($prevMouvements);

        $ordonnanceEvolution = $this->evolutionPercent($stats['ordonnance_montant'], $prevStats['ordonnance_montant']);
        $recouvrementsEvolution = $this->evolutionPercent($stats['recouvrements_montant'], $prevStats['recouvrements_montant']);

        return [
            'periode' => ['annee' => $annee, 'mois' => $mois, 'date_debut' => null, 'date_fin' => null],
            'indicateurs' => [
                'taux_execution' => $stats['taux_execution'],
                'taux_rejet' => $stats['taux_rejet'],
                'mandats_total' => $stats['mandats_total'],
                'mandats_admis' => $stats['mandats_admis'],
                'mandats_rejetes' => $stats['mandats_rejetes'],
                'tresorerie_reelle_total' => $central['global']['tresorerie_reelle'],
                'recouvrements_4121_total' => $central['global']['total_recouvrements_4121'],
                'ordonnance_total' => $central['global']['total_ordonnance'],
                'montant_paye_total' => $central['global']['total_montant_paye'],
                'solde_total' => $central['global']['solde'],
            ],
            'comparaison_mois_precedent' => [
                'ordonnance_evolution_pct' => $ordonnanceEvolution,
                'recouvrements_evolution_pct' => $recouvrementsEvolution,
                'mandats_evolution_pct' => $this->evolutionPercent($stats['mandats_total'], $prevStats['mandats_total']),
            ],
            'performance_regions' => $this->performanceRegions($annee, $mois, $regionCode),
            'meta' => $central['meta'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function alertes(
        ?int $annee = null,
        ?int $mois = null,
        ?string $dateDebut = null,
        ?string $dateFin = null,
        ?string $regionCode = null,
    ): array {
        $useDateRange = $dateDebut !== null;

        if ($useDateRange) {
            $dateFin = $dateFin ?? $dateDebut;
            $mouvements = $this->mouvementsForDateRange($dateDebut, $dateFin, $regionCode);
            $annee = (int) substr($dateDebut, 0, 4);
            $mois = null;
        } else {
            [$annee, $mois] = $this->resolvePeriod($annee, $mois);
            $mouvements = $this->mouvementsForPeriod($annee, $mois, $regionCode);
        }

        $alertes = [];
        $stats = $this->computeMouvementStats($mouvements);

        if ($stats['mandats_total'] > 0 && $stats['taux_rejet'] >= self::SEUIL_REJET_CRITIQUE) {
            $alertes[] = $this->alert(
                'rejet_critique',
                'critique',
                'rejets',
                'Taux de rejets critique',
                sprintf('Le taux de rejets atteint %.1f%% (%d mandats).', $stats['taux_rejet'], $stats['mandats_rejetes']),
                'Analyser immédiatement les causes principales de rejet.',
            );
        } elseif ($stats['mandats_total'] > 0 && $stats['taux_rejet'] >= self::SEUIL_REJET_WARNING) {
            $alertes[] = $this->alert(
                'rejet_warning',
                'warning',
                'rejets',
                'Taux de rejets élevé',
                sprintf('Le taux de rejets est de %.1f%%.', $stats['taux_rejet']),
                'Surveiller l\'évolution et identifier les motifs fréquents.',
            );
        }

        if ($stats['mandats_admis'] > self::SEUIL_MANDATS_ATTENTE) {
            $alertes[] = $this->alert(
                'attente_elevee',
                $stats['mandats_admis'] > 200 ? 'critique' : 'warning',
                'workflow',
                'Volume élevé de mandats en attente',
                sprintf('%d mandats admis en attente de traitement.', $stats['mandats_admis']),
                'Accélérer le traitement ou identifier les goulots d\'étranglement.',
            );
        }

        $importants = $mouvements->filter(fn (Mouvement $m) => (float) $m->montant >= self::SEUIL_MONTANT_IMPORTANT);
        if ($importants->count() > 0) {
            $alertes[] = $this->alert(
                'paiements_importants',
                $importants->count() > 10 ? 'critique' : 'warning',
                'montants',
                'Paiements importants (≥ 6 M FCFA)',
                sprintf('%d opérations ≥ 6 millions FCFA ce mois.', $importants->count()),
                'Vérifier la conformité et accélérer les dossiers en attente.',
            );
        }

        foreach ($this->activeRegions($regionCode) as $region) {
            $hasData = $useDateRange
                ? $this->mouvementsForRegionDateRange($region, $dateDebut, $dateFin)->isNotEmpty()
                : $this->resolveDashboard($region, $annee, $mois) !== null;

            if (!$hasData) {
                $alertes[] = $this->alert(
                    'region_sans_donnees_' . $region->code,
                    'info',
                    'donnees',
                    'Région sans données',
                    sprintf('%s (%s) : aucune donnée pour la période.', $region->nom, $region->code),
                    'Vérifier la connectivité Push côté AICE-API.',
                );
            }
        }

        usort($alertes, fn ($a, $b) => $this->priorityWeight($b['priorite']) <=> $this->priorityWeight($a['priorite']));

        return $alertes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function anomalies(
        ?int $annee = null,
        ?int $mois = null,
        ?string $dateDebut = null,
        ?string $dateFin = null,
        ?string $regionCode = null,
    ): array {
        if ($dateDebut !== null) {
            $dateFin = $dateFin ?? $dateDebut;
            $performances = $this->performanceRegionsForDateRange($dateDebut, $dateFin, $regionCode);
        } else {
            [$annee, $mois] = $this->resolvePeriod($annee, $mois);
            $performances = $this->performanceRegions($annee, $mois, $regionCode);
        }

        $anomalies = [];

        if ($performances === []) {
            return [];
        }

        $avgRejet = collect($performances)->avg('taux_rejet') ?? 0;

        foreach ($performances as $row) {
            if ($row['mandats_total'] < 5) {
                continue;
            }

            if ($row['taux_rejet'] > $avgRejet + 10) {
                $anomalies[] = [
                    'type' => 'taux_rejet',
                    'region_code' => $row['region']['code'],
                    'region_nom' => $row['region']['nom'],
                    'description' => sprintf(
                        'Taux de rejet anormalement élevé (%.1f%%, moyenne nationale %.1f%%).',
                        $row['taux_rejet'],
                        $avgRejet,
                    ),
                    'valeur' => $row['taux_rejet'],
                    'severite' => $row['taux_rejet'] >= self::SEUIL_REJET_CRITIQUE ? 'elevee' : 'moderee',
                ];
            }

            if ($row['taux_execution'] < 50 && $row['mandats_total'] >= 10) {
                $anomalies[] = [
                    'type' => 'execution_faible',
                    'region_code' => $row['region']['code'],
                    'region_nom' => $row['region']['nom'],
                    'description' => sprintf('Taux d\'exécution faible (%.1f%%).', $row['taux_execution']),
                    'valeur' => $row['taux_execution'],
                    'severite' => 'moderee',
                ];
            }
        }

        return $anomalies;
    }

    /**
     * @return array<string, mixed>
     */
    public function predictions(
        ?int $annee = null,
        ?int $mois = null,
        ?string $dateDebut = null,
        ?string $dateFin = null,
        ?string $regionCode = null,
    ): array {
        if ($dateDebut !== null) {
            $dateFin = $dateFin ?? $dateDebut;
            $mouvements = $this->mouvementsForDateRange($dateDebut, $dateFin, $regionCode);
            $stats = $this->computeMouvementStats($mouvements);
            $prev = $this->previousDateRange($dateDebut, $dateFin);
            $prevStats = $this->computeMouvementStats($this->mouvementsForDateRange($prev['debut'], $prev['fin'], $regionCode));
            $depensesEvolution = $this->evolutionPercent($stats['ordonnance_montant'], $prevStats['ordonnance_montant']);

            $tendance = 'stable';
            if ($depensesEvolution !== null) {
                if ($depensesEvolution > 5) {
                    $tendance = 'hausse';
                } elseif ($depensesEvolution < -5) {
                    $tendance = 'baisse';
                }
            }

            $daysInRange = Carbon::parse($dateDebut)->diffInDays(Carbon::parse($dateFin)) + 1;
            $daysElapsed = min(
                $daysInRange,
                max(1, Carbon::parse($dateDebut)->diffInDays(min(Carbon::parse($dateFin), Carbon::now())) + 1),
            );
            $projection = ($stats['ordonnance_montant'] / $daysElapsed) * $daysInRange;

            return [
                'tendance_depenses' => [
                    'type' => $tendance,
                    'evolution_pct' => $depensesEvolution,
                    'description' => match ($tendance) {
                        'hausse' => 'Les dépenses progressent par rapport à la période précédente.',
                        'baisse' => 'Les dépenses reculent par rapport à la période précédente.',
                        default => 'Les dépenses sont stables par rapport à la période précédente.',
                    },
                ],
                'projection_depenses_fin_mois' => round($projection, 2),
                'depenses_mois_courant' => $stats['ordonnance_montant'],
            ];
        }

        [$annee, $mois] = $this->resolvePeriod($annee, $mois);
        $mouvements = $this->mouvementsForPeriod($annee, $mois, $regionCode);
        $stats = $this->computeMouvementStats($mouvements);

        $prev = $this->previousPeriod($annee, $mois);
        $prevStats = $this->computeMouvementStats($this->mouvementsForPeriod($prev['annee'], $prev['mois'], $regionCode));

        $depensesEvolution = $this->evolutionPercent($stats['ordonnance_montant'], $prevStats['ordonnance_montant']);

        $tendance = 'stable';
        if ($depensesEvolution !== null) {
            if ($depensesEvolution > 5) {
                $tendance = 'hausse';
            } elseif ($depensesEvolution < -5) {
                $tendance = 'baisse';
            }
        }

        $jourDuMois = Carbon::create($annee, $mois, 1)->daysInMonth;
        $jourActuel = min(Carbon::now()->day, $jourDuMois);
        $projectionFinMois = $jourActuel > 0
            ? ($stats['ordonnance_montant'] / $jourActuel) * $jourDuMois
            : 0;

        return [
            'tendance_depenses' => [
                'type' => $tendance,
                'evolution_pct' => $depensesEvolution,
                'description' => match ($tendance) {
                    'hausse' => 'Les dépenses progressent par rapport au mois précédent.',
                    'baisse' => 'Les dépenses reculent par rapport au mois précédent.',
                    default => 'Les dépenses sont stables par rapport au mois précédent.',
                },
            ],
            'projection_depenses_fin_mois' => round($projectionFinMois, 2),
            'depenses_mois_courant' => $stats['ordonnance_montant'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function performanceRegionsForDateRange(string $dateDebut, string $dateFin, ?string $regionCode = null): array
    {
        $rows = [];

        foreach ($this->activeRegions($regionCode) as $region) {
            $mouvements = $this->mouvementsForRegionDateRange($region, $dateDebut, $dateFin);
            if ($mouvements->isEmpty()) {
                continue;
            }

            $stats = $this->computeMouvementStats($mouvements);

            $rows[] = [
                'region' => ['code' => $region->code, 'nom' => $region->nom],
                'taux_execution' => $stats['taux_execution'],
                'taux_rejet' => $stats['taux_rejet'],
                'mandats_total' => $stats['mandats_total'],
                'score' => $this->regionScore($stats),
            ];
        }

        usort($rows, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $rows;
    }

    /** @return Collection<int, Mouvement> */
    private function mouvementsForDateRange(string $dateDebut, string $dateFin, ?string $regionCode = null): Collection
    {
        $dashboardIds = $this->dashboardIdsQuery($regionCode)->pluck('id');

        if ($dashboardIds->isEmpty()) {
            return collect();
        }

        return Mouvement::query()
            ->whereIn('dashboard_id', $dashboardIds)
            ->whereBetween('date_mouvement', [$dateDebut, $dateFin])
            ->get();
    }

    /** @return Collection<int, Mouvement> */
    private function mouvementsForRegionDateRange(Region $region, string $dateDebut, string $dateFin): Collection
    {
        $dashboardIds = Dashboard::query()
            ->where('region_id', $region->id)
            ->pluck('id');

        if ($dashboardIds->isEmpty()) {
            return collect();
        }

        return Mouvement::query()
            ->whereIn('dashboard_id', $dashboardIds)
            ->whereBetween('date_mouvement', [$dateDebut, $dateFin])
            ->get();
    }

    /** @return array{debut: string, fin: string} */
    private function previousDateRange(string $dateDebut, string $dateFin): array
    {
        $start = Carbon::parse($dateDebut);
        $end = Carbon::parse($dateFin);
        $days = $start->diffInDays($end) + 1;
        $prevEnd = $start->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1);

        return [
            'debut' => $prevStart->toDateString(),
            'fin' => $prevEnd->toDateString(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function performanceRegions(int $annee, int $mois, ?string $regionCode = null): array
    {
        $rows = [];

        foreach ($this->activeRegions($regionCode) as $region) {
            $dashboard = $this->resolveDashboard($region, $annee, $mois);
            if (!$dashboard) {
                continue;
            }

            $mouvements = $this->mouvementsForDashboard($dashboard, $annee, $mois);
            $stats = $this->computeMouvementStats($mouvements);

            $rows[] = [
                'region' => ['code' => $region->code, 'nom' => $region->nom],
                'taux_execution' => $stats['taux_execution'],
                'taux_rejet' => $stats['taux_rejet'],
                'mandats_total' => $stats['mandats_total'],
                'score' => $this->regionScore($stats),
            ];
        }

        usort($rows, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $rows;
    }

    /** @param Collection<int, Mouvement> $mouvements */
    private function computeMouvementStats(Collection $mouvements): array
    {
        $stats = MandatCounter::computeHierarchyStats($mouvements);
        $stats['mandats_total'] = MandatCounter::navMandatLines($mouvements)->count();

        return $stats;
    }

    /** @return array{annee: int, mois: int} */
    private function resolvePeriod(?int $annee, ?int $mois): array
    {
        $now = Carbon::now();

        return [
            $annee ?? $now->year,
            $mois ?? $now->month,
        ];
    }

    /** @return array{annee: int, mois: int} */
    private function previousPeriod(int $annee, int $mois): array
    {
        $date = Carbon::create($annee, $mois, 1)->subMonth();

        return ['annee' => $date->year, 'mois' => $date->month];
    }

    /** @return Collection<int, Mouvement> */
    private function mouvementsForPeriod(int $annee, int $mois, ?string $regionCode = null): Collection
    {
        $dashboardIds = $this->dashboardIdsQuery($regionCode)
            ->where('annee', $annee)
            ->where('mois', $mois)
            ->pluck('id');

        if ($dashboardIds->isEmpty()) {
            return collect();
        }

        return Mouvement::query()
            ->whereIn('dashboard_id', $dashboardIds)
            ->where('annee', $annee)
            ->where('mois', $mois)
            ->get();
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

    /** @return \Illuminate\Database\Eloquent\Builder<Dashboard> */
    private function dashboardIdsQuery(?string $regionCode = null)
    {
        $query = Dashboard::query();

        if ($regionCode !== null && $regionCode !== '') {
            $regionId = Region::query()->where('code', $regionCode)->value('id');
            if ($regionId === null) {
                return Dashboard::query()->whereRaw('0 = 1');
            }

            $query->where('region_id', $regionId);
        }

        return $query;
    }

    private function resolveDashboard(Region $region, int $annee, int $mois): ?Dashboard
    {
        return Dashboard::query()
            ->where('region_id', $region->id)
            ->where('annee', $annee)
            ->where('mois', $mois)
            ->orderByDesc('updated_at')
            ->first();
    }

    /** @return Collection<int, Mouvement> */
    private function mouvementsForDashboard(Dashboard $dashboard, int $annee, int $mois): Collection
    {
        return Mouvement::query()
            ->where('dashboard_id', $dashboard->id)
            ->where('annee', $annee)
            ->where('mois', $mois)
            ->get();
    }

    private function evolutionPercent(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /** @param array<string, float|int> $stats */
    private function regionScore(array $stats): int
    {
        $execution = (float) ($stats['taux_execution'] ?? 0);
        $rejet = (float) ($stats['taux_rejet'] ?? 0);

        return (int) max(0, min(100, round($execution - ($rejet * 1.5))));
    }

    private function priorityWeight(string $priorite): int
    {
        return match ($priorite) {
            'critique' => 3,
            'warning' => 2,
            default => 1,
        };
    }

    /** @return array<string, mixed> */
    private function alert(
        string $id,
        string $priorite,
        string $categorie,
        string $titre,
        string $message,
        string $action,
    ): array {
        return [
            'id' => $id,
            'priorite' => $priorite,
            'categorie' => $categorie,
            'titre' => $titre,
            'message' => $message,
            'action_recommandee' => $action,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
