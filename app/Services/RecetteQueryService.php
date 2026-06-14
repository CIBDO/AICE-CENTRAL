<?php

namespace App\Services;

use App\Models\Mouvement;
use App\Support\DetailQueryFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class RecetteQueryService
{
    private const EXPORT_LIMIT = 5000;

    private const GL_ACCOUNT_4121 = '4121';

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $rows = $this->collectRows($filters);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, (int) ($filters['per_page'] ?? 15));

        $items = $rows
            ->forPage($page, $perPage)
            ->map(fn (Mouvement $m) => $this->toRecetteRow($m))
            ->values();

        return new Paginator($items, $rows->count(), $perPage, $page);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function exportRows(array $filters): Collection
    {
        return $this->collectRows($filters)
            ->take(self::EXPORT_LIMIT)
            ->map(fn (Mouvement $m) => $this->toRecetteRow($m))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function stats(array $filters): array
    {
        $rows = $this->collectRows($filters);

        $topClients = $rows
            ->groupBy(fn (Mouvement $m) => $this->clientKey($m))
            ->map(function (Collection $group, string $key) {
                $first = $group->first();

                return [
                    'client_no' => $this->clientNo($first),
                    'client_name' => $this->clientName($first),
                    'count' => $group->count(),
                    'montant' => (float) $group->sum('montant'),
                ];
            })
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
                'clients_uniques' => $rows->map(fn (Mouvement $m) => $this->clientKey($m))->unique()->count(),
                'montant_total' => $total,
                'montant_moyen' => $rows->count() > 0 ? round($total / $rows->count(), 2) : 0,
                'top_client_part_pct' => $topShare,
            ],
            'top_clients' => $topClients,
            'par_jour' => $this->groupByDay($rows),
        ];
    }

    /**
     * Écritures GL 4121 (1 ligne NAV = 1 entry_no), dédupliquées entre pushs.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Mouvement>
     */
    private function collectRows(array $filters): Collection
    {
        return $this->dedupeRecettes(
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

        $query = Mouvement::query()
            ->whereIn('dashboard_id', $dashboardIds)
            ->where('type', 'recette')
            ->where('regional_id', 'like', 'RECETTE-%');

        DetailQueryFilters::applyDateRange($query, $filters, 'date_mouvement', supportsAnneeMois: true);

        if (!empty($filters['client_no'])) {
            $this->applyClientFilter($query, (string) $filters['client_no']);
        }

        return DetailQueryFilters::applySearch($query, $filters, [
            'libelle', 'beneficiaire', 'code_programme', 'source_id', 'nature',
        ]);
    }

    /**
     * @param  Collection<int, Mouvement>  $rows
     * @return Collection<int, Mouvement>
     */
    private function dedupeRecettes(Collection $rows): Collection
    {
        return $rows
            ->sortByDesc('id')
            ->unique(fn (Mouvement $m) => filled($m->source_id) ? 'entry:' . $m->source_id : 'regional:' . $m->regional_id)
            ->sortByDesc(fn (Mouvement $m) => $m->date_mouvement?->format('Y-m-d') ?? '')
            ->sortByDesc('id')
            ->values();
    }

    private function clientKey(Mouvement $m): string
    {
        $no = trim((string) ($m->code_programme ?? ''));

        if ($no !== '') {
            return $no;
        }

        $name = trim((string) ($m->beneficiaire ?? ''));

        if ($name !== '') {
            return $name;
        }

        return DetailQueryFilters::EMPTY_LABEL;
    }

    private function clientNo(Mouvement $m): string
    {
        return $this->clientKey($m);
    }

    private function clientName(Mouvement $m): string
    {
        $name = trim((string) ($m->beneficiaire ?? ''));

        if ($name !== '') {
            return $name;
        }

        $no = trim((string) ($m->code_programme ?? ''));

        return $no !== '' ? "Client {$no}" : DetailQueryFilters::EMPTY_LABEL;
    }

    private function applyClientFilter(Builder $query, string $clientNo): Builder
    {
        if ($clientNo === DetailQueryFilters::EMPTY_LABEL) {
            return $query->where(function (Builder $q) {
                $q->where(function (Builder $inner) {
                    $inner->whereNull('code_programme')->orWhere('code_programme', '');
                })->where(function (Builder $inner) {
                    $inner->whereNull('beneficiaire')->orWhere('beneficiaire', '');
                });
            });
        }

        return $query->where(function (Builder $q) use ($clientNo) {
            $q->where('code_programme', $clientNo)
                ->orWhere('beneficiaire', $clientNo);
        });
    }

    /** @return array<string, mixed> */
    private function toRecetteRow(Mouvement $m): array
    {
        return [
            'id' => $m->id,
            'client_no' => $this->clientNo($m),
            'client_name' => $this->clientName($m),
            'montant' => (float) $m->montant,
            'date_posting' => $m->date_mouvement?->format('Y-m-d'),
            'gl_account' => self::GL_ACCOUNT_4121,
            'description' => $m->libelle,
        ];
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
