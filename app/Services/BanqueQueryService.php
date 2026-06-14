<?php

namespace App\Services;

use App\Models\BanquePush;
use App\Support\DetailQueryFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class BanqueQueryService
{
    private const EXPORT_LIMIT = 5000;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $rows = $this->collectRows($filters);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, (int) ($filters['per_page'] ?? 15));

        return new Paginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, BanquePush>
     */
    public function exportRows(array $filters): Collection
    {
        return $this->collectRows($filters)->take(self::EXPORT_LIMIT)->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function stats(array $filters): array
    {
        $rows = $this->collectRows($filters);

        $totalDebit = (float) $rows->sum('debit');
        $totalCredit = (float) $rows->sum('credit');

        return [
            'totaux' => [
                'count' => $rows->count(),
                'comptes_uniques' => $rows->pluck('numero_compte')->unique()->count(),
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'flux_net' => $totalCredit - $totalDebit,
            ],
            'par_compte' => $rows
                ->groupBy('numero_compte')
                ->map(fn (Collection $group, string $compte) => [
                    'numero_compte' => $compte,
                    'libelle' => $group->first()->libelle,
                    'count' => $group->count(),
                    'debit' => (float) $group->sum('debit'),
                    'credit' => (float) $group->sum('credit'),
                    'solde' => (float) $this->latestRow($group)->solde,
                ])
                ->sortByDesc('credit')
                ->values()
                ->all(),
            'par_jour' => $rows
                ->groupBy(fn (BanquePush $b) => $b->date_mouvement?->format('Y-m-d') ?? 'sans-date')
                ->map(fn (Collection $group, string $date) => [
                    'date' => $date,
                    'debit' => (float) $group->sum('debit'),
                    'credit' => (float) $group->sum('credit'),
                    'count' => $group->count(),
                ])
                ->sortBy('date')
                ->values()
                ->all(),
        ];
    }

    /**
     * Écritures ledger banque (1 ligne NAV = 1 entry_no), dédupliquées entre pushs.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, BanquePush>
     */
    private function collectRows(array $filters): Collection
    {
        return $this->dedupeBanques(
            $this->baseQuery($filters)
                ->orderByDesc('date_mouvement')
                ->orderByDesc('id')
                ->get()
        );
    }

    /** @param  array<string, mixed>  $filters */
    private function baseQuery(array $filters): Builder
    {
        [, , $dashboardIds] = DetailQueryFilters::resolveContext($filters);

        $query = BanquePush::query()->whereIn('dashboard_id', $dashboardIds);

        DetailQueryFilters::applyDateRange($query, $filters, 'date_mouvement', supportsAnneeMois: true);

        if (!empty($filters['numero_compte'])) {
            $query->where('numero_compte', $filters['numero_compte']);
        }

        return DetailQueryFilters::applySearch($query, $filters, [
            'libelle', 'numero_compte', 'reference', 'description', 'type_document',
        ]);
    }

    /**
     * @param  Collection<int, BanquePush>  $rows
     * @return Collection<int, BanquePush>
     */
    private function dedupeBanques(Collection $rows): Collection
    {
        return $rows
            ->sortByDesc('id')
            ->unique(fn (BanquePush $b) => filled($b->entry_no) ? 'entry:' . $b->entry_no : 'regional:' . $b->regional_id)
            ->sortByDesc(fn (BanquePush $b) => $b->date_mouvement?->format('Y-m-d') ?? '')
            ->sortByDesc('id')
            ->values();
    }

    /** @param  Collection<int, BanquePush>  $group */
    private function latestRow(Collection $group): BanquePush
    {
        return $group
            ->sortByDesc(fn (BanquePush $b) => $b->date_mouvement?->format('Y-m-d') ?? '')
            ->sortByDesc('id')
            ->first();
    }
}
