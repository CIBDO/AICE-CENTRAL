<?php

namespace App\Services;

use App\Models\Mouvement;
use App\Support\DetailQueryFilters;
use App\Support\MandatCounter;
use App\Support\StatutNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class NatureCeQueryService
{
    private const EXPORT_LIMIT = 5000;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->orderByDesc('date_mouvement')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Mouvement>
     */
    public function exportRows(array $filters): Collection
    {
        return $this->baseQuery($filters)
            ->orderByDesc('date_mouvement')
            ->orderByDesc('id')
            ->limit(self::EXPORT_LIMIT)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function stats(array $filters): array
    {
        $allRows = MandatCounter::dedupeRows(
            $this->baseQuery($filters, applyNatureCeFilter: false)->get()
        );
        $filteredRows = MandatCounter::dedupeRows($this->baseQuery($filters)->get());
        // Alignement strict avec la source NAV: les compteurs Nature CE utilisent
        // les lignes comptables NAV (et non les mandats distincts dédupliqués).
        $allMandats = MandatCounter::navMandatLines($allRows);
        $filteredMandats = MandatCounter::navMandatLines($filteredRows);
        $financial = MandatCounter::financialTotals($filteredRows);

        $statut = fn (Mouvement $m): string => StatutNormalizer::normalize($m->statut, $m->statut_code) ?? '';
        $payes = $filteredMandats->filter(
            fn (Mouvement $m) => in_array($statut($m), ['Payé', 'Réglé'], true)
        )->count();
        $mandatsCount = $filteredMandats->count();

        return [
            'totaux' => [
                'natures_ce_count' => $this->buildNatureCeList($allMandats)->count(),
                'mandats_count' => $mandatsCount,
                'montant_ordonnance' => $financial['total_ordonnance'],
                'montant_recouvrements_4121' => $financial['total_recouvrements_4121'],
                'taux_execution_pct' => $mandatsCount > 0
                    ? round(($payes / $mandatsCount) * 100, 1)
                    : 0,
            ],
            'natures_ce' => $this->buildNatureCeList($allMandats)->values()->all(),
            'par_statut' => array_map(
                fn (array $row) => ['label' => $row['statut'], 'count' => $row['count'], 'montant' => $row['montant']],
                MandatCounter::parStatut($filteredRows)
            ),
            'par_chapitre' => $this->groupStat($filteredMandats, 'chapitre', 10),
            'par_type_mandat' => array_map(
                fn (array $row) => ['label' => $row['libelle'], 'code' => $row['code'], 'count' => $row['count'], 'montant' => $row['montant']],
                MandatCounter::parType($filteredRows)
            ),
            'par_jour' => $this->groupByDay($filteredMandats),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function baseQuery(array $filters, bool $applyNatureCeFilter = true): Builder
    {
        [, , $dashboardIds] = DetailQueryFilters::resolveContext($filters);

        $query = Mouvement::query()->whereIn('dashboard_id', $dashboardIds);

        DetailQueryFilters::applyDateRange($query, $filters, 'date_mouvement', supportsAnneeMois: true);

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if ($applyNatureCeFilter && !empty($filters['nature_ce'])) {
            $this->applyEffectiveNatureCeFilter($query, (string) $filters['nature_ce']);
        }

        if (!empty($filters['statut'])) {
            if ($filters['statut'] === 'Rejeté') {
                $query->where('statut', 'like', '%Rejet%');
            } else {
                $query->where('statut', $filters['statut']);
            }
        }

        if (!empty($filters['chapitre'])) {
            DetailQueryFilters::applyEmptyableFieldFilter($query, 'chapitre', $filters['chapitre']);
        }

        if (!empty($filters['programme'])) {
            $query->where('code_programme', $filters['programme']);
        }

        return DetailQueryFilters::applySearch($query, $filters, [
            'libelle', 'nature_ce', 'nature', 'chapitre', 'beneficiaire', 'statut', 'code_programme',
        ]);
    }

    /** @param Collection<int, Mouvement> $rows */
    private function buildNatureCeList(Collection $rows): Collection
    {
        $statut = fn (Mouvement $m): string => StatutNormalizer::normalize($m->statut, $m->statut_code) ?? '';

        return $rows
            ->groupBy(fn (Mouvement $m) => self::effectiveNatureCeCode($m))
            ->map(function (Collection $group, string $code) use ($statut) {
                $payes = $group->filter(
                    fn (Mouvement $m) => in_array($statut($m), ['Payé', 'Réglé'], true)
                )->count();
                $count = $group->count();
                $first = $group->first();

                return [
                    'code' => $code,
                    'libelle' => $first ? self::effectiveNatureCeLabel($first) : ('Nature CE ' . $code),
                    'count' => $count,
                    'montant_depenses' => (float) $group->sum(fn (Mouvement $m) => StatutNormalizer::montantForStatut($m)),
                    'paye_count' => $payes,
                    'admis_count' => $group->filter(fn (Mouvement $m) => $statut($m) === 'Admis')->count(),
                    'taux_execution_pct' => $count > 0 ? round(($payes / $count) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('montant_depenses');
    }

    private function applyEffectiveNatureCeFilter(Builder $query, string $value): Builder
    {
        if ($value === DetailQueryFilters::EMPTY_LABEL) {
            return $query->where(function (Builder $q) {
                $q->where(function (Builder $inner) {
                    $inner->whereNull('nature_ce')->orWhere('nature_ce', '');
                })->where(function (Builder $inner) {
                    $inner->whereNull('nature')->orWhere('nature', '');
                });
            });
        }

        return $query->where(function (Builder $q) use ($value) {
            $q->where('nature_ce', $value)
                ->orWhere(function (Builder $fallback) use ($value) {
                    $fallback->where(function (Builder $inner) {
                        $inner->whereNull('nature_ce')->orWhere('nature_ce', '');
                    })->where('nature', $value);
                });
        });
    }

    private static function effectiveNatureCeCode(Mouvement $m): string
    {
        $natureCe = trim((string) ($m->nature_ce ?? ''));
        if ($natureCe !== '') {
            return $natureCe;
        }

        $nature = trim((string) ($m->nature ?? ''));
        if ($nature !== '') {
            return $nature;
        }

        return DetailQueryFilters::EMPTY_LABEL;
    }

    private static function effectiveNatureCeLabel(Mouvement $m): string
    {
        $nature = trim((string) ($m->nature ?? ''));
        if ($nature !== '') {
            return $nature;
        }

        return self::effectiveNatureCeCode($m);
    }

    /** @param Collection<int, Mouvement> $rows */
    private function groupStat(Collection $rows, string $field, ?int $limit = null): array
    {
        $result = $rows
            ->groupBy(fn (Mouvement $m) => $m->{$field} ?: DetailQueryFilters::EMPTY_LABEL)
            ->map(fn (Collection $group, string $key) => [
                'label' => $key,
                'count' => $group->count(),
                'montant' => (float) $group->sum('montant'),
            ])
            ->sortByDesc('montant')
            ->values();

        if ($limit) {
            $result = $result->take($limit);
        }

        return $result->all();
    }

    /** @param Collection<int, Mouvement> $rows */
    private function groupTypeMandat(Collection $rows): array
    {
        $labels = ['0' => 'Matériel', '1' => 'Salaire', '2' => 'Reversement'];

        return collect($labels)->map(function (string $label, string $code) use ($rows) {
            $subset = $rows->where('type_mandat', $code);

            return [
                'label' => $label,
                'code' => $code,
                'count' => $subset->count(),
                'montant' => (float) $subset->sum('montant'),
            ];
        })->values()->all();
    }

    /** @param Collection<int, Mouvement> $rows */
    private function groupByDay(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (Mouvement $m) => $m->date_mouvement?->format('Y-m-d') ?? 'sans-date')
            ->map(fn (Collection $group, string $date) => [
                'date' => $date,
                'count' => $group->count(),
                'montant' => (float) $group->sum('montant'),
            ])
            ->sortBy('date')
            ->values()
            ->all();
    }
}
