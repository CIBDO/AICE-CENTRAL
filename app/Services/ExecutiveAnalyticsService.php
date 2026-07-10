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
    private const DEFAULT_COMPARE_MODE = 'mois_precedent';

    private const DEFAULT_SLA_WARNING_DAYS = 7;

    private const DEFAULT_SLA_CRITICAL_DAYS = 15;

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
        ?string $compareMode = null,
        ?int $slaWarningDays = null,
        ?int $slaCriticalDays = null,
    ): array {
        $config = $this->config($compareMode, $slaWarningDays, $slaCriticalDays);

        if ($dateDebut !== null) {
            $dateFin = $dateFin ?? $dateDebut;
            $mouvements = $this->mouvementsForDateRange($dateDebut, $dateFin, $regionCode);
            $central = app(CentralAggregationService::class)->summary(null, null, $dateDebut, $dateFin, $regionCode);
            $prev = $this->comparisonDateRange($dateDebut, $dateFin, $config['compare_mode']);
            $prevMouvements = $this->mouvementsForDateRange($prev['debut'], $prev['fin'], $regionCode);
            $stats = $this->computeMouvementStats($mouvements);
            $prevStats = $this->computeMouvementStats($prevMouvements);
            $workflowAging = $this->workflowAging(
                $mouvements,
                $this->referenceDateForDateRange($dateFin),
                $config['sla_warning_days'],
                $config['sla_critical_days'],
            );

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
                'parametres' => $config,
                'workflow' => MandatCounter::workflowBacklog($mouvements),
                'workflow_aging' => $workflowAging,
                'comparaison_reference' => [
                    'mode' => $config['compare_mode'],
                    'label' => $config['compare_label'],
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
        $workflowAging = $this->workflowAging(
            $mouvements,
            $this->referenceDateForMonth($annee, $mois),
            $config['sla_warning_days'],
            $config['sla_critical_days'],
        );

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
            'parametres' => $config,
            'workflow' => MandatCounter::workflowBacklog($mouvements),
            'workflow_aging' => $workflowAging,
            'comparaison_reference' => [
                'mode' => $config['compare_mode'],
                'label' => $config['compare_label'],
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
        ?string $compareMode = null,
        ?int $slaWarningDays = null,
        ?int $slaCriticalDays = null,
    ): array {
        $config = $this->config($compareMode, $slaWarningDays, $slaCriticalDays);
        $useDateRange = $dateDebut !== null;

        if ($useDateRange) {
            $dateFin = $dateFin ?? $dateDebut;
            $mouvements = $this->mouvementsForDateRange($dateDebut, $dateFin, $regionCode);
            $annee = (int) substr($dateDebut, 0, 4);
            $mois = null;
            $referenceDate = $this->referenceDateForDateRange($dateFin);
        } else {
            [$annee, $mois] = $this->resolvePeriod($annee, $mois);
            $mouvements = $this->mouvementsForPeriod($annee, $mois, $regionCode);
            $referenceDate = $this->referenceDateForMonth($annee, $mois);
        }

        $alertes = [];
        $stats = $this->computeMouvementStats($mouvements);
        $workflowAging = $this->workflowAging(
            $mouvements,
            $referenceDate,
            $config['sla_warning_days'],
            $config['sla_critical_days'],
        );

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

        if ($workflowAging['count'] > 0 && $workflowAging['max_days'] > $config['sla_critical_days']) {
            $alertes[] = $this->alert(
                'sla_workflow_critique',
                'critique',
                'workflow',
                'SLA workflow critique',
                sprintf(
                    'Des mandats en cours atteignent %d jours (seuil critique > %d jours).',
                    $workflowAging['max_days'],
                    $config['sla_critical_days'],
                ),
                'Prioriser les dossiers les plus anciens et traiter les blocages workflow.',
            );
        } elseif ($workflowAging['count'] > 0 && $workflowAging['max_days'] > $config['sla_warning_days']) {
            $alertes[] = $this->alert(
                'sla_workflow_warning',
                'warning',
                'workflow',
                'SLA workflow en vigilance',
                sprintf(
                    'Les mandats en cours atteignent %d jours (seuil warning > %d jours).',
                    $workflowAging['max_days'],
                    $config['sla_warning_days'],
                ),
                'Surveiller les encours et anticiper les retards avant le seuil critique.',
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
        ?string $compareMode = null,
        ?int $slaWarningDays = null,
        ?int $slaCriticalDays = null,
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
        ?string $compareMode = null,
        ?int $slaWarningDays = null,
        ?int $slaCriticalDays = null,
    ): array {
        $config = $this->config($compareMode, $slaWarningDays, $slaCriticalDays);
        if ($dateDebut !== null) {
            $dateFin = $dateFin ?? $dateDebut;
            $mouvements = $this->mouvementsForDateRange($dateDebut, $dateFin, $regionCode);
            $stats = $this->computeMouvementStats($mouvements);
            $prev = $this->comparisonDateRange($dateDebut, $dateFin, $config['compare_mode']);
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
                        'hausse' => sprintf('Les dépenses progressent par rapport au %s.', $config['compare_label']),
                        'baisse' => sprintf('Les dépenses reculent par rapport au %s.', $config['compare_label']),
                        default => sprintf('Les dépenses sont stables par rapport au %s.', $config['compare_label']),
                    },
                ],
                'reference_label' => $config['compare_label'],
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
                    'hausse' => sprintf('Les dépenses progressent par rapport au %s.', $config['compare_label']),
                    'baisse' => sprintf('Les dépenses reculent par rapport au %s.', $config['compare_label']),
                    default => sprintf('Les dépenses sont stables par rapport au %s.', $config['compare_label']),
                },
            ],
            'reference_label' => $config['compare_label'],
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

    /** @return array{debut: string, fin: string} */
    private function previousMonthDateRange(string $dateDebut, string $dateFin): array
    {
        $start = Carbon::parse($dateDebut)->subMonthNoOverflow();
        $end = Carbon::parse($dateFin)->subMonthNoOverflow();

        return [
            'debut' => $start->toDateString(),
            'fin' => $end->toDateString(),
        ];
    }

    /** @return array{debut: string, fin: string} */
    private function comparisonDateRange(string $dateDebut, string $dateFin, string $compareMode): array
    {
        return $compareMode === 'periode_precedente'
            ? $this->previousDateRange($dateDebut, $dateFin)
            : $this->previousMonthDateRange($dateDebut, $dateFin);
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

    private function referenceDateForDateRange(string $dateFin): Carbon
    {
        $reference = Carbon::parse($dateFin)->endOfDay();

        return $reference->greaterThan(now()) ? now() : $reference;
    }

    private function referenceDateForMonth(int $annee, int $mois): Carbon
    {
        $reference = Carbon::create($annee, $mois, 1)->endOfMonth()->endOfDay();

        return $reference->greaterThan(now()) ? now() : $reference;
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

    /** @return array{compare_mode: string, compare_label: string, sla_warning_days: int, sla_critical_days: int} */
    private function config(?string $compareMode, ?int $slaWarningDays, ?int $slaCriticalDays): array
    {
        $mode = in_array($compareMode, ['mois_precedent', 'periode_precedente'], true)
            ? $compareMode
            : self::DEFAULT_COMPARE_MODE;

        $warning = max(1, $slaWarningDays ?? self::DEFAULT_SLA_WARNING_DAYS);
        $critical = max($warning + 1, $slaCriticalDays ?? self::DEFAULT_SLA_CRITICAL_DAYS);

        return [
            'compare_mode' => $mode,
            'compare_label' => $mode === 'periode_precedente' ? 'période précédente' : 'mois précédent',
            'sla_warning_days' => $warning,
            'sla_critical_days' => $critical,
        ];
    }

    /**
     * @param  Collection<int, Mouvement>  $mouvements
     * @return array{
     *     count: int,
     *     average_days: int,
     *     max_days: int,
     *     warning_days: int,
     *     critical_days: int,
     *     over_warning_count: int,
     *     over_critical_count: int,
     *     reference_date: string
     * }
     */
    private function workflowAging(Collection $mouvements, Carbon $referenceDate, int $warningDays, int $criticalDays): array
    {
        $ages = MandatCounter::mandatsForStats($mouvements)
            ->filter(fn (Mouvement $m) => $this->isWorkflowPending($m))
            ->map(fn (Mouvement $m) => $this->workflowAgeInDays($m, $referenceDate))
            ->values();

        $count = $ages->count();

        return [
            'count' => $count,
            'average_days' => $count > 0 ? (int) round((float) $ages->avg()) : 0,
            'max_days' => $count > 0 ? (int) $ages->max() : 0,
            'warning_days' => $warningDays,
            'critical_days' => $criticalDays,
            'over_warning_count' => $count > 0 ? $ages->filter(fn (int $days) => $days > $warningDays)->count() : 0,
            'over_critical_count' => $count > 0 ? $ages->filter(fn (int $days) => $days > $criticalDays)->count() : 0,
            'reference_date' => $referenceDate->toDateString(),
        ];
    }

    private function isWorkflowPending(Mouvement $mouvement): bool
    {
        $statut = \App\Support\StatutNormalizer::normalize($mouvement->statut, $mouvement->statut_code) ?? '';

        if ($statut === '') {
            return false;
        }

        if (str_contains($statut, 'Rejet')) {
            return false;
        }

        return ! in_array($statut, ['Payé', 'Réglé'], true);
    }

    private function workflowAgeInDays(Mouvement $mouvement, Carbon $referenceDate): int
    {
        $origin = null;

        if ($mouvement->date_mouvement) {
            $origin = Carbon::parse($mouvement->date_mouvement);
        } elseif ($mouvement->annee && $mouvement->mois) {
            $origin = Carbon::create((int) $mouvement->annee, (int) $mouvement->mois, 1);
        }

        if ($origin === null) {
            return 0;
        }

        return max(0, $origin->startOfDay()->diffInDays($referenceDate->copy()->startOfDay()));
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
