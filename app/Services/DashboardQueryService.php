<?php

namespace App\Services;

use App\Models\Dashboard;
use App\Models\Mouvement;
use App\Models\RecetteClientPush;
use App\Models\Region;
use App\Support\DashboardKpis;
use App\Support\MandatCounter;
use Illuminate\Support\Collection;

class DashboardQueryService
{
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

        $mouvements = Mouvement::query()
            ->whereIn('dashboard_id', $dashboardIds)
            ->whereBetween('date_mouvement', [$dateDebut, $dateFin])
            ->get();

        if ($mouvements->isEmpty()) {
            return $this->emptySummary($region, $dateDebut, $dateFin);
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
        ], $latestDashboard ? (float) $latestDashboard->tresorerie_reelle : 0.0);

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
            'mandats_par_type' => $this->mandatsParType($mouvements),
            'statuts_mandats' => $this->statutsMandats($mouvements),
            'meta' => [
                'dashboard_id' => $latestDashboard?->id,
                'regional_id' => $latestDashboard?->regional_id,
                'derniere_mise_a_jour' => $latestDashboard?->updated_at?->toIso8601String(),
                'mouvements_count' => MandatCounter::dedupeRows($mouvements)->count(),
            ],
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
        return [
            'region' => [
                'code' => $region->code,
                'nom' => $region->nom,
            ],
            'periode' => $periode,
            'kpis' => DashboardKpis::fromDashboard($dashboard),
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
    ): array {
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
            'kpis' => DashboardKpis::empty(),
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
