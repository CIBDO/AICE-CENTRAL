<?php

namespace App\Services;

use App\Models\BanquePush;
use App\Support\BankMovementAmounts;
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
     * @return Collection<int, array<string, mixed>>
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
                'flux_net' => $totalDebit - $totalCredit,
            ],
            'par_compte' => $rows
                ->groupBy('numero_compte')
                ->map(fn (Collection $group, string $compte) => [
                    'numero_compte' => $compte,
                    'libelle' => $group->first()['libelle'] ?? '',
                    'count' => $group->count(),
                    'debit' => (float) $group->sum('debit'),
                    'credit' => (float) $group->sum('credit'),
                    'solde' => (float) ($group->sortByDesc('date_mouvement')->sortByDesc('id')->first()['solde'] ?? 0),
                ])
                ->sortByDesc('debit')
                ->values()
                ->all(),
            'par_jour' => $rows
                ->groupBy(fn (array $row) => $row['date_mouvement'] ?? 'sans-date')
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
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function collectRows(array $filters): Collection
    {
        [, , $dashboardIds] = DetailQueryFilters::resolveContext($filters);

        $hasLineLevel = BanquePush::query()
            ->whereIn('dashboard_id', $dashboardIds)
            ->where('regional_id', 'like', 'BANQUE-ENTRY-%')
            ->exists();

        return $this->dedupeBanques(
            $this->baseQuery($filters, $hasLineLevel)
                ->orderByDesc('date_mouvement')
                ->orderByDesc('id')
                ->get()
        )->map(fn (BanquePush $b) => $this->toBanqueRow($b))->values();
    }

    /** @param  array<string, mixed>  $filters */
    private function baseQuery(array $filters, bool $lineLevelOnly): Builder
    {
        [, , $dashboardIds] = DetailQueryFilters::resolveContext($filters);

        $query = BanquePush::query()->whereIn('dashboard_id', $dashboardIds);

        if ($lineLevelOnly) {
            $query->where('regional_id', 'like', 'BANQUE-ENTRY-%');
        }

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

    /** @return array<string, mixed> */
    private function toBanqueRow(BanquePush $b): array
    {
        [$debit, $credit] = BankMovementAmounts::toStateConvention(
            (float) $b->debit,
            (float) $b->credit,
        );

        return [
            'id' => $b->id,
            'numero_compte' => $b->numero_compte,
            'libelle' => $b->libelle,
            'date_mouvement' => $b->date_mouvement?->format('Y-m-d'),
            'debit' => $debit,
            'credit' => $credit,
            'solde' => (float) $b->solde,
            'reference' => $b->reference,
            'entry_no' => $b->entry_no,
            'type_document' => $b->type_document,
            'description' => $b->description,
            'flux' => $debit - $credit,
        ];
    }
}
