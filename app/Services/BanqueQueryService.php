<?php

namespace App\Services;

use App\Models\BanquePush;
use App\Support\DetailQueryFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BanqueQueryService
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
     * @return Collection<int, BanquePush>
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
        $rows = $this->baseQuery($filters)->get();

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
                    'solde' => (float) $group->last()->solde,
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

    /** @param  array<string, mixed>  $filters */
    private function baseQuery(array $filters): Builder
    {
        [, , $dashboardIds] = DetailQueryFilters::resolveContext($filters);

        $query = BanquePush::query()->whereIn('dashboard_id', $dashboardIds);

        if (!empty($filters['numero_compte'])) {
            $query->where('numero_compte', $filters['numero_compte']);
        }

        return DetailQueryFilters::applySearch($query, $filters, [
            'libelle', 'numero_compte', 'reference', 'description', 'type_document',
        ]);
    }
}
