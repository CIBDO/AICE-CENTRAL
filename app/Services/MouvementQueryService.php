<?php

namespace App\Services;

use App\Models\Mouvement;
use App\Support\DetailQueryFilters;
use App\Support\MandatCounter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MouvementQueryService
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
     */
    public function find(int $id, array $filters): ?Mouvement
    {
        [, , $dashboardIds] = DetailQueryFilters::resolveContext($filters);

        return Mouvement::query()
            ->whereIn('dashboard_id', $dashboardIds)
            ->with(['dashboard.region'])
            ->find($id);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Mouvement>
     */
    public function related(Mouvement $mouvement, array $filters, int $limit = 6): Collection
    {
        [, , $dashboardIds] = DetailQueryFilters::resolveContext($filters);

        return Mouvement::query()
            ->whereIn('dashboard_id', $dashboardIds)
            ->where('id', '!=', $mouvement->id)
            ->when(
                $mouvement->code_programme,
                fn (Builder $q) => $q->where('code_programme', $mouvement->code_programme),
            )
            ->orderByDesc('date_mouvement')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function stats(array $filters): array
    {
        $rows = MandatCounter::dedupeRows($this->baseQuery($filters)->get());
        $financial = MandatCounter::financialTotals($rows);
        $mandats = MandatCounter::mandatsForStats($rows);

        return [
            'totaux' => [
                'count' => $rows->count(),
                'depenses_count' => $mandats->count(),
                'recettes_count' => $rows->where('type', 'recette')->count(),
                'montant_ordonnance' => $financial['total_ordonnance'],
                'montant_recouvrements_4121' => $financial['total_recouvrements_4121'],
                'montant_total' => $financial['total_ordonnance'] + $financial['total_recouvrements_4121'],
            ],
            'par_statut' => array_map(
                fn (array $row) => ['label' => $row['statut'], 'count' => $row['count'], 'montant' => $row['montant']],
                MandatCounter::parStatut($rows)
            ),
            'par_type_mandat' => array_map(
                fn (array $row) => ['label' => $row['libelle'], 'code' => $row['code'], 'count' => $row['count'], 'montant' => $row['montant']],
                MandatCounter::parType($rows)
            ),
            'par_programme' => $this->groupStat($mandats, 'code_programme', 8),
            'par_jour' => $this->groupByDay($rows),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function baseQuery(array $filters): Builder
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

        if (!empty($filters['statut'])) {
            if ($filters['statut'] === 'Rejeté') {
                $query->where('statut', 'like', '%Rejet%');
            } else {
                $query->where('statut', $filters['statut']);
            }
        }

        if (isset($filters['type_mandat']) && $filters['type_mandat'] !== '') {
            $query->where('type_mandat', $filters['type_mandat']);
        }

        if (!empty($filters['programme'])) {
            $query->where('code_programme', $filters['programme']);
        }

        return DetailQueryFilters::applySearch($query, $filters, [
            'libelle', 'beneficiaire', 'statut', 'code_programme', 'nature_ce', 'source_numero_mandat',
        ]);
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
