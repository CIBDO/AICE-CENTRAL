<?php

namespace App\Services;

use App\Models\BanquePush;
use App\Support\BankMovementAmounts;
use App\Support\DetailQueryFilters;
use Carbon\Carbon;
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
    public function filteredBalance(array $filters): float
    {
        $rows = $this->queryRows($filters);

        if ($rows->isEmpty()) {
            return 0.0;
        }

        return $this->latestBalancesPerAccount($this->dedupeBanques($rows));
    }

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
     * @return array<string, mixed>
     */
    public function overview(array $filters): array
    {
        $rawVisibleRows = $this->queryRows($filters);
        $visibleRows = $this->dedupeBanques($rawVisibleRows)->map(fn (BanquePush $b) => $this->toBanqueRow($b))->values();

        if ($visibleRows->isEmpty()) {
            return [
                'pont_tresorerie' => [
                    'solde_debut' => 0.0,
                    'encaissements' => 0.0,
                    'decaissements' => 0.0,
                    'solde_fin' => 0.0,
                ],
                'evolution' => [],
                'top_variations' => [],
                'anomalies' => [],
                'confiance' => [
                    'derniere_date_mouvement' => null,
                    'comptes_inclus' => 0,
                    'lignes_incluses' => 0,
                    'lignes_exclues' => 0,
                ],
            ];
        }

        $contextRows = $this->queryContextRows($filters);
        $periodStart = $this->periodStart($filters, $visibleRows);
        $accountSnapshots = $this->accountSnapshots($visibleRows, $contextRows, $periodStart);

        $soldeDebut = (float) $accountSnapshots->sum('solde_debut');
        $encaissements = (float) $visibleRows->sum('debit');
        $decaissements = (float) $visibleRows->sum('credit');
        $soldeFin = (float) $accountSnapshots->sum('solde_fin');
        return [
            'pont_tresorerie' => [
                'solde_debut' => $soldeDebut,
                'encaissements' => $encaissements,
                'decaissements' => $decaissements,
                'solde_fin' => $soldeFin,
            ],
            'evolution' => $this->treasuryTimeline($visibleRows, $soldeDebut),
            'top_variations' => $accountSnapshots
                ->sortByDesc(fn (array $row) => abs((float) $row['variation']))
                ->take(5)
                ->values()
                ->all(),
            'anomalies' => $this->bankAnomalies($accountSnapshots, $encaissements, $decaissements),
            'confiance' => [
                'derniere_date_mouvement' => $visibleRows->pluck('date_mouvement')->filter()->max(),
                'comptes_inclus' => $accountSnapshots->count(),
                'lignes_incluses' => $visibleRows->count(),
                'lignes_exclues' => max(0, $rawVisibleRows->count() - $visibleRows->count()),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function collectRows(array $filters): Collection
    {
        return $this->dedupeBanques(
            $this->queryRows($filters)
        )->map(fn (BanquePush $b) => $this->toBanqueRow($b))->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function queryContextRows(array $filters): Collection
    {
        $contextFilters = array_diff_key($filters, array_flip(['date_debut', 'date_fin', 'annee', 'mois', 'page', 'per_page']));
        [, , $dashboardIds] = DetailQueryFilters::resolveContext($contextFilters);

        $hasLineLevel = BanquePush::query()
            ->whereIn('dashboard_id', $dashboardIds)
            ->where('regional_id', 'like', 'BANQUE-ENTRY-%')
            ->exists();

        return $this->dedupeBanques(
            $this->baseQuery($contextFilters, $hasLineLevel)
                ->orderByDesc('date_mouvement')
                ->orderByDesc('id')
                ->get()
        )->map(fn (BanquePush $b) => $this->toBanqueRow($b))->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, BanquePush>
     */
    private function queryRows(array $filters): Collection
    {
        [, , $dashboardIds] = DetailQueryFilters::resolveContext($filters);

        $hasLineLevel = BanquePush::query()
            ->whereIn('dashboard_id', $dashboardIds)
            ->where('regional_id', 'like', 'BANQUE-ENTRY-%')
            ->exists();

        return $this->baseQuery($filters, $hasLineLevel)
            ->orderByDesc('date_mouvement')
            ->orderByDesc('id')
            ->get();
    }

    /** @param  array<string, mixed>  $filters */
    private function baseQuery(array $filters, bool $lineLevelOnly): Builder
    {
        [, , $dashboardIds] = DetailQueryFilters::resolveContext($filters);

        $query = BanquePush::query()->whereIn('dashboard_id', $dashboardIds);

        if ($lineLevelOnly) {
            $query->where('regional_id', 'like', 'BANQUE-ENTRY-%');
        }

        $this->applyBanqueDateRange($query, $filters);

        if (!empty($filters['numero_compte'])) {
            $query->where('numero_compte', $filters['numero_compte']);
        }

        return DetailQueryFilters::applySearch($query, $filters, [
            'libelle', 'numero_compte', 'reference', 'description', 'type_document',
        ]);
    }

    /** @param  array<string, mixed>  $filters */
    private function applyBanqueDateRange(Builder $query, array $filters): Builder
    {
        if (!empty($filters['date_debut'])) {
            $dateDebut = (string) $filters['date_debut'];
            $dateFin = (string) ($filters['date_fin'] ?? $dateDebut);
            $annee = (int) substr($dateDebut, 0, 4);
            $isFullYear = $dateDebut === "{$annee}-01-01" && $dateFin === "{$annee}-12-31";

            return $query->where(function (Builder $q) use ($dateDebut, $dateFin, $annee, $isFullYear) {
                $q->whereBetween('date_mouvement', [$dateDebut, $dateFin]);

                // Certains pushes bancaires historiques ne portent pas de date mais gardent l'exercice.
                if ($isFullYear) {
                    $q->orWhere(function (Builder $fallback) use ($annee) {
                        $fallback->whereNull('date_mouvement')
                            ->where('exercice', $annee);
                    });
                }
            });
        }

        if (!empty($filters['annee'])) {
            $annee = (int) $filters['annee'];

            $query->where(function (Builder $q) use ($annee) {
                $q->whereYear('date_mouvement', $annee)
                    ->orWhere(function (Builder $fallback) use ($annee) {
                        $fallback->whereNull('date_mouvement')
                            ->where('exercice', $annee);
                    });
            });
        }

        if (!empty($filters['mois'])) {
            $query->whereMonth('date_mouvement', (int) $filters['mois']);
        }

        return $query;
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

    /**
     * @param  Collection<int, BanquePush>  $rows
     */
    private function latestBalancesPerAccount(Collection $rows): float
    {
        return (float) $rows
            ->filter(fn (BanquePush $b) => filled($b->numero_compte))
            ->groupBy('numero_compte')
            ->map(function (Collection $group) {
                /** @var BanquePush|null $latest */
                $latest = $group
                    ->sortByDesc(fn (BanquePush $b) => $b->date_mouvement?->format('Y-m-d') ?? '')
                    ->sortByDesc('id')
                    ->first();

                return (float) ($latest?->solde ?? 0);
            })
            ->sum();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $visibleRows
     * @param  Collection<int, array<string, mixed>>  $contextRows
     * @return Collection<int, array<string, mixed>>
     */
    private function accountSnapshots(Collection $visibleRows, Collection $contextRows, ?Carbon $periodStart): Collection
    {
        return $visibleRows
            ->filter(fn (array $row) => filled($row['numero_compte']))
            ->groupBy('numero_compte')
            ->map(function (Collection $group, string $numeroCompte) use ($contextRows, $periodStart) {
                $sorted = $group->sortBy(fn (array $row) => $this->rowSortKey($row))->values();
                $first = $sorted->first();
                $last = $sorted->last();
                $accountContext = $contextRows
                    ->filter(fn (array $row) => ($row['numero_compte'] ?? null) === $numeroCompte)
                    ->sortBy(fn (array $row) => $this->rowSortKey($row))
                    ->values();

                $beforeStart = $periodStart
                    ? $accountContext->filter(function (array $row) use ($periodStart) {
                        $date = $row['date_mouvement'] ?? null;

                        return $date !== null && Carbon::parse($date)->lt($periodStart);
                    })->last()
                    : null;

                $soldeDebut = $beforeStart !== null
                    ? (float) ($beforeStart['solde'] ?? 0)
                    : (float) (($first['solde'] ?? 0) - ($first['flux'] ?? 0));
                $soldeFin = (float) ($last['solde'] ?? 0);

                return [
                    'numero_compte' => $numeroCompte,
                    'libelle' => $last['libelle'] ?? ($first['libelle'] ?? ''),
                    'count' => $sorted->count(),
                    'encaissements' => (float) $sorted->sum('debit'),
                    'decaissements' => (float) $sorted->sum('credit'),
                    'solde_debut' => $soldeDebut,
                    'solde_fin' => $soldeFin,
                    'variation' => $soldeFin - $soldeDebut,
                    'derniere_date_mouvement' => $last['date_mouvement'] ?? null,
                ];
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function treasuryTimeline(Collection $rows, float $soldeDebut): array
    {
        $days = $rows
            ->filter(fn (array $row) => filled($row['date_mouvement']))
            ->groupBy('date_mouvement')
            ->map(fn (Collection $group, string $date) => [
                'date' => $date,
                'encaissements' => (float) $group->sum('debit'),
                'decaissements' => (float) $group->sum('credit'),
                'flux_net' => (float) $group->sum('flux'),
                'count' => $group->count(),
            ])
            ->sortBy('date')
            ->values();

        $runningBalance = $soldeDebut;

        return $days
            ->map(function (array $row) use (&$runningBalance) {
                $runningBalance += (float) $row['flux_net'];

                return $row + ['solde' => $runningBalance];
            })
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $accountSnapshots
     * @return array<int, array<string, mixed>>
     */
    private function bankAnomalies(
        Collection $accountSnapshots,
        float $encaissements,
        float $decaissements,
    ): array {
        $negativeBalances = $accountSnapshots
            ->filter(fn (array $row) => (float) $row['solde_fin'] < 0)
            ->sortBy('solde_fin')
            ->take(3)
            ->values();

        $gaps = $accountSnapshots
            ->filter(function (array $row) {
                $encaissements = (float) $row['encaissements'];
                $decaissements = (float) $row['decaissements'];
                $maxFlux = max($encaissements, $decaissements);
                $minFlux = min($encaissements, $decaissements);

                return $maxFlux >= 1_000_000 && ($minFlux === 0.0 || $maxFlux / max($minFlux, 1.0) >= 3);
            })
            ->sortByDesc(fn (array $row) => abs((float) $row['encaissements'] - (float) $row['decaissements']))
            ->take(3)
            ->values();

        $anomalies = [];

        if ($negativeBalances->isNotEmpty()) {
            $anomalies[] = [
                'type' => 'solde_negatif',
                'priorite' => 'critique',
                'titre' => 'Comptes en solde negatif',
                'detail' => sprintf('%d compte(s) affichent un solde final négatif.', $negativeBalances->count()),
            ];
        }

        if ($gaps->isNotEmpty() || max($encaissements, $decaissements) >= 1_000_000 && min($encaissements, $decaissements) === 0.0) {
            $anomalies[] = [
                'type' => 'ecart_flux',
                'priorite' => 'warning',
                'titre' => 'Ecart inhabituel entre encaissements et decaissements',
                'detail' => sprintf('Encaissements %s FCFA vs décaissements %s FCFA sur la période.', number_format($encaissements, 0, ',', ' '), number_format($decaissements, 0, ',', ' ')),
            ];
        }

        return $anomalies;
    }

    /** @param  array<string, mixed>  $filters */
    private function periodStart(array $filters, Collection $visibleRows): ?Carbon
    {
        if (!empty($filters['date_debut'])) {
            return Carbon::parse((string) $filters['date_debut'])->startOfDay();
        }

        if (!empty($filters['annee'])) {
            return Carbon::create((int) $filters['annee'], (int) ($filters['mois'] ?? 1), 1)->startOfDay();
        }

        $firstDate = $visibleRows->pluck('date_mouvement')->filter()->sort()->first();

        return $firstDate ? Carbon::parse((string) $firstDate)->startOfDay() : null;
    }

    /** @param  array<string, mixed>  $row */
    private function rowSortKey(array $row): string
    {
        return sprintf(
            '%s|%010d',
            (string) ($row['date_mouvement'] ?? '0000-01-01'),
            (int) ($row['id'] ?? 0),
        );
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
