<?php

namespace App\Support;

use App\Models\Dashboard;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DetailQueryFilters
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: int, 1: int, 2: Collection<int, int>}
     */
    public static function resolveContext(array $filters): array
    {
        $annee = (int) ($filters['annee'] ?? now()->year);
        $mois = isset($filters['mois']) ? (int) $filters['mois'] : now()->month;

        $regions = Region::query()->actives()->ordered();

        if (!empty($filters['region_code'])) {
            $regions->where('code', $filters['region_code']);
        }

        $regionIds = $regions->pluck('id');

        $dashboardIds = Dashboard::query()
            ->whereIn('region_id', $regionIds)
            ->where('annee', $annee)
            ->when($mois, fn (Builder $q) => $q->where('mois', $mois))
            ->pluck('id');

        return [$annee, $mois, $dashboardIds];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function applySearch(Builder $query, array $filters, array $columns): Builder
    {
        if (empty($filters['search'])) {
            return $query;
        }

        $term = '%' . $filters['search'] . '%';

        return $query->where(function (Builder $q) use ($columns, $term) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', $term);
            }
        });
    }
}
