<?php

namespace App\Services;

use App\Models\Mouvement;
use App\Support\DetailQueryFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProgrammeQueryService
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
        $allDepenses = $this->baseQuery($filters, applyProgrammeFilter: false)
            ->where('type', 'depense')
            ->get();

        $filtered = $this->baseQuery($filters)->get();
        $depenses = $filtered->where('type', 'depense');
        $recettes = $filtered->where('type', 'recette');

        $payes = $depenses->filter(fn (Mouvement $m) => $m->statut === 'Payé')->count();
        $depenseTotal = (float) $depenses->sum('montant');

        return [
            'totaux' => [
                'programmes_count' => $this->buildProgrammeList($allDepenses)->count(),
                'mandats_count' => $depenses->count(),
                'montant_depenses' => $depenseTotal,
                'montant_recettes' => (float) $recettes->sum('montant'),
                'taux_execution_pct' => $depenses->count() > 0
                    ? round(($payes / $depenses->count()) * 100, 1)
                    : 0,
            ],
            'programmes' => $this->buildProgrammeList($allDepenses)->values()->all(),
            'par_statut' => $this->groupStat($depenses, 'statut'),
            'par_chapitre' => $this->groupStat($depenses, 'chapitre', 10),
            'par_type_mandat' => $this->groupTypeMandat($depenses),
            'par_jour' => $this->groupByDay($depenses),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function baseQuery(array $filters, bool $applyProgrammeFilter = true): Builder
    {
        [, , $dashboardIds] = DetailQueryFilters::resolveContext($filters);

        $query = Mouvement::query()->whereIn('dashboard_id', $dashboardIds);

        if (!empty($filters['annee'])) {
            $query->where('annee', (int) $filters['annee']);
        }

        if (!empty($filters['mois'])) {
            $query->where('mois', (int) $filters['mois']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if ($applyProgrammeFilter && !empty($filters['programme'])) {
            $query->where('code_programme', $filters['programme']);
        }

        if (!empty($filters['statut'])) {
            if ($filters['statut'] === 'Rejeté') {
                $query->where('statut', 'like', '%Rejet%');
            } else {
                $query->where('statut', $filters['statut']);
            }
        }

        if (!empty($filters['chapitre'])) {
            $query->where('chapitre', $filters['chapitre']);
        }

        return DetailQueryFilters::applySearch($query, $filters, [
            'libelle', 'programme', 'code_programme', 'chapitre', 'beneficiaire', 'statut',
        ]);
    }

    /** @param Collection<int, Mouvement> $rows */
    private function buildProgrammeList(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (Mouvement $m) => $m->code_programme ?: 'Non renseigné')
            ->map(function (Collection $group, string $code) {
                $payes = $group->where('statut', 'Payé')->count();
                $count = $group->count();

                return [
                    'code' => $code,
                    'libelle' => $group->first()->programme ?: ('Programme ' . $code),
                    'count' => $count,
                    'montant_depenses' => (float) $group->sum('montant'),
                    'paye_count' => $payes,
                    'admis_count' => $group->where('statut', 'Admis')->count(),
                    'taux_execution_pct' => $count > 0 ? round(($payes / $count) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('montant_depenses');
    }

    /** @param Collection<int, Mouvement> $rows */
    private function groupStat(Collection $rows, string $field, ?int $limit = null): array
    {
        $result = $rows
            ->groupBy(fn (Mouvement $m) => $m->{$field} ?: 'Non renseigné')
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
