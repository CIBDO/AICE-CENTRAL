<?php

namespace App\Services;

use App\Models\Dashboard;
use App\Models\Mouvement;
use App\Models\Region;
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
    public function kpis(?int $annee = null, ?int $mois = null): array
    {
        [$annee, $mois] = $this->resolvePeriod($annee, $mois);
        $mouvements = $this->mouvementsForPeriod($annee, $mois);
        $central = app(CentralAggregationService::class)->summary($annee, $mois);

        $stats = $this->computeMouvementStats($mouvements);
        $prev = $this->previousPeriod($annee, $mois);
        $prevMouvements = $this->mouvementsForPeriod($prev['annee'], $prev['mois']);
        $prevStats = $this->computeMouvementStats($prevMouvements);

        $depensesEvolution = $this->evolutionPercent($stats['depenses_montant'], $prevStats['depenses_montant']);
        $recettesEvolution = $this->evolutionPercent($stats['recettes_montant'], $prevStats['recettes_montant']);

        return [
            'periode' => ['annee' => $annee, 'mois' => $mois],
            'indicateurs' => [
                'taux_execution' => $stats['taux_execution'],
                'taux_rejet' => $stats['taux_rejet'],
                'mandats_total' => $stats['mandats_total'],
                'mandats_admis' => $stats['mandats_admis'],
                'mandats_rejetes' => $stats['mandats_rejetes'],
                'encaisse_total' => $central['global']['encaisse'],
                'recettes_total' => $central['global']['total_recettes'],
                'depenses_total' => $central['global']['total_depenses'],
                'solde_total' => $central['global']['solde'],
            ],
            'comparaison_mois_precedent' => [
                'depenses_evolution_pct' => $depensesEvolution,
                'recettes_evolution_pct' => $recettesEvolution,
                'mandats_evolution_pct' => $this->evolutionPercent($stats['mandats_total'], $prevStats['mandats_total']),
            ],
            'performance_regions' => $this->performanceRegions($annee, $mois),
            'meta' => $central['meta'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function alertes(?int $annee = null, ?int $mois = null): array
    {
        [$annee, $mois] = $this->resolvePeriod($annee, $mois);
        $alertes = [];

        $mouvements = $this->mouvementsForPeriod($annee, $mois);
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

        foreach (Region::query()->actives()->ordered()->get() as $region) {
            if (!$this->resolveDashboard($region, $annee, $mois)) {
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
    public function anomalies(?int $annee = null, ?int $mois = null): array
    {
        [$annee, $mois] = $this->resolvePeriod($annee, $mois);
        $anomalies = [];
        $performances = $this->performanceRegions($annee, $mois);

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
    public function predictions(?int $annee = null, ?int $mois = null): array
    {
        [$annee, $mois] = $this->resolvePeriod($annee, $mois);
        $mouvements = $this->mouvementsForPeriod($annee, $mois);
        $stats = $this->computeMouvementStats($mouvements);

        $prev = $this->previousPeriod($annee, $mois);
        $prevStats = $this->computeMouvementStats($this->mouvementsForPeriod($prev['annee'], $prev['mois']));

        $depensesEvolution = $this->evolutionPercent($stats['depenses_montant'], $prevStats['depenses_montant']);

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
            ? ($stats['depenses_montant'] / $jourActuel) * $jourDuMois
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
            'depenses_mois_courant' => $stats['depenses_montant'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function performanceRegions(int $annee, int $mois): array
    {
        $rows = [];

        foreach (Region::query()->actives()->ordered()->get() as $region) {
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
        $depenses = $mouvements->where('type', 'depense');
        $recettes = $mouvements->where('type', 'recette');

        $mandatsTotal = $depenses->count();
        $mandatsRejetes = $depenses->filter(fn (Mouvement $m) => str_contains((string) $m->statut, 'Rejet'))->count();
        $mandatsAdmis = $depenses->filter(fn (Mouvement $m) => ($m->statut ?? '') === 'Admis')->count();
        $mandatsPayes = $depenses->filter(fn (Mouvement $m) => in_array($m->statut ?? '', ['Payé', 'Réglé'], true))->count();

        $depensesMontant = (float) $depenses->sum('montant');
        $recettesMontant = (float) $recettes->sum('montant');
        $montantPaye = (float) $depenses->filter(fn (Mouvement $m) => in_array($m->statut ?? '', ['Payé', 'Réglé'], true))->sum('montant');
        $montantTotal = $depensesMontant;

        return [
            'mandats_total' => $mandatsTotal,
            'mandats_rejetes' => $mandatsRejetes,
            'mandats_admis' => $mandatsAdmis,
            'mandats_payes' => $mandatsPayes,
            'depenses_montant' => $depensesMontant,
            'recettes_montant' => $recettesMontant,
            'taux_rejet' => $mandatsTotal > 0 ? round(($mandatsRejetes / $mandatsTotal) * 100, 1) : 0.0,
            'taux_execution' => $montantTotal > 0 ? round(($montantPaye / $montantTotal) * 100, 1) : 0.0,
        ];
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
    private function mouvementsForPeriod(int $annee, int $mois): Collection
    {
        $dashboardIds = Dashboard::query()
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
