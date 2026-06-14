<?php

namespace App\Services;

use App\Models\RecetteClientPush;
use App\Support\DetailQueryFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RecetteQueryService
{
    private const EXPORT_LIMIT = 5000;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->orderByDesc('date_posting')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, RecetteClientPush>
     */
    public function exportRows(array $filters): Collection
    {
        return $this->baseQuery($filters)
            ->orderByDesc('date_posting')
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

        $topClients = $rows
            ->groupBy('client_no')
            ->map(fn (Collection $group) => [
                'client_no' => $group->first()->client_no,
                'client_name' => $group->first()->client_name,
                'count' => $group->count(),
                'montant' => (float) $group->sum('montant'),
            ])
            ->sortByDesc('montant')
            ->take(10)
            ->values()
            ->all();

        $total = (float) $rows->sum('montant');
        $topShare = $total > 0 && $topClients !== []
            ? round(($topClients[0]['montant'] / $total) * 100, 1)
            : 0;

        return [
            'totaux' => [
                'count' => $rows->count(),
                'clients_uniques' => $rows->pluck('client_no')->unique()->count(),
                'montant_total' => $total,
                'montant_moyen' => $rows->count() > 0 ? round($total / $rows->count(), 2) : 0,
                'top_client_part_pct' => $topShare,
            ],
            'top_clients' => $topClients,
            'par_jour' => $this->groupByDay($rows),
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function baseQuery(array $filters): Builder
    {
        [, , $dashboardIds] = DetailQueryFilters::resolveContext($filters);

        $query = RecetteClientPush::query()->whereIn('dashboard_id', $dashboardIds);

        DetailQueryFilters::applyDateRange($query, $filters, 'date_posting');

        if (!empty($filters['client_no'])) {
            $query->where('client_no', $filters['client_no']);
        }

        return DetailQueryFilters::applySearch($query, $filters, [
            'client_name', 'client_no', 'description', 'gl_account', 'source_no',
        ]);
    }

    /** @param Collection<int, RecetteClientPush> $rows */
    private function groupByDay(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (RecetteClientPush $r) => $r->date_posting?->format('Y-m-d') ?? 'sans-date')
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
