<?php

namespace App\Support;

use App\Models\Dashboard;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DetailQueryFilters
{
    public const EMPTY_LABEL = 'Non renseigné';

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: int, 1: int, 2: Collection<int, int>}
     */
    public static function resolveContext(array $filters): array
    {
        $dateDebut = $filters['date_debut'] ?? null;
        $annee = isset($filters['annee'])
            ? (int) $filters['annee']
            : ($dateDebut ? (int) substr((string) $dateDebut, 0, 4) : now()->year);
        $mois = isset($filters['mois']) ? (int) $filters['mois'] : null;

        $regions = Region::query()->actives()->ordered();

        if (!empty($filters['region_code'])) {
            $regions->where('code', $filters['region_code']);
        }

        $regionIds = $regions->pluck('id');

        $dashboardQuery = Dashboard::query()->whereIn('region_id', $regionIds);

        if ($dateDebut === null) {
            $dashboardQuery->where('annee', $annee);
            if ($mois) {
                $dashboardQuery->where('mois', $mois);
            }
        }

        $dashboardIds = $dashboardQuery->pluck('id');

        return [$annee, $mois, $dashboardIds];
    }

    /**
     * Filtre par plage de dates (prioritaire) ou par année / mois sur la colonne date.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function applyDateRange(
        Builder $query,
        array $filters,
        string $dateColumn,
        bool $supportsAnneeMois = false,
    ): Builder {
        if (!empty($filters['date_debut'])) {
            $fin = $filters['date_fin'] ?? $filters['date_debut'];
            $annee = (int) substr((string) $filters['date_debut'], 0, 4);
            $isFullYear = $filters['date_debut'] === "{$annee}-01-01" && $fin === "{$annee}-12-31";

            if ($isFullYear && $supportsAnneeMois) {
                return $query->where('annee', $annee);
            }

            return $query->where(function (Builder $inner) use ($dateColumn, $filters, $fin, $annee, $supportsAnneeMois) {
                $inner->whereBetween($dateColumn, [$filters['date_debut'], $fin]);
                if ($supportsAnneeMois) {
                    $inner->orWhere(function (Builder $fallback) use ($annee) {
                        $fallback->where('annee', $annee)->whereNull($dateColumn);
                    });
                }
            });
        }

        if ($supportsAnneeMois) {
            if (!empty($filters['annee'])) {
                $query->where('annee', (int) $filters['annee']);
            }

            if (!empty($filters['mois'])) {
                $query->where('mois', (int) $filters['mois']);
            }
        }

        return $query;
    }

    /**
     * Filtre une colonne nullable dont la valeur vide est affichée comme « Non renseigné ».
     */
    public static function applyEmptyableFieldFilter(Builder $query, string $column, mixed $value): Builder
    {
        if ($value === null || $value === '') {
            return $query;
        }

        $value = (string) $value;

        if ($value === self::EMPTY_LABEL) {
            return $query->where(function (Builder $q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });
        }

        return $query->where($column, $value);
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
