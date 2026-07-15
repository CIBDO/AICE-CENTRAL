<?php

namespace App\Support;

use App\Models\Mouvement;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MandatCounter
{
    /** @var array<string, string> */
    public const TYPE_LABELS = [
        '0' => 'Matériel',
        '1' => 'Salaire',
        '2' => 'Reversement',
    ];

    /**
     * Élimine les doublons issus de pushs multiples (même regional_id).
     *
     * @param  Collection<int, Mouvement>  $mouvements
     * @return Collection<int, Mouvement>
     */
    public static function dedupeRows(Collection $mouvements): Collection
    {
        [$withRegionalId, $withoutRegionalId] = $mouvements->partition(
            fn (Mouvement $m) => filled($m->regional_id)
        );

        return $withRegionalId->unique('regional_id')->merge($withoutRegionalId)->values();
    }

    /**
     * Mandats uniques pour les statistiques hiérarchiques (central / exécutif / explorateurs).
     *
     * @param  Collection<int, Mouvement>  $mouvements
     * @return Collection<int, Mouvement>
     */
    public static function mandatsForStats(Collection $mouvements): Collection
    {
        return self::dedupeForCount(
            self::filterMandats(self::dedupeRows($mouvements))->filter(
                fn (Mouvement $m) => ! StatutNormalizer::isExcluded($m->statut, $m->statut_code)
            )
        );
    }

    /**
     * @param  Collection<int, Mouvement>  $mouvements
     * @return array{
     *     total_ordonnance: float,
     *     total_recouvrements_4121: float,
     *     total_montant_paye: float,
     *     solde: float
     * }
     */
    public static function financialTotals(Collection $mouvements): array
    {
        $rows = self::dedupeRows($mouvements);

        $recouvrements = (float) $rows
            ->where('type', 'recette')
            ->sum(fn (Mouvement $m) => (float) $m->montant);

        // Ordonnancé = somme des lignes NAV (MP_MT_BRUT_ORD), aligné écran Mandats / Types de mandats.
        $mandatLines = self::filterMandats($rows)->filter(
            fn (Mouvement $m) => self::isNavLineCountable($m)
        );
        $ordonnance = (float) $mandatLines->sum(fn (Mouvement $m) => (float) $m->montant);
        $montantPaye = self::montantPayeTotal($rows);

        return [
            'total_ordonnance' => $ordonnance,
            'total_recouvrements_4121' => $recouvrements,
            'total_montant_paye' => $montantPaye,
            'solde' => $recouvrements - $ordonnance,
        ];
    }

    /**
     * Montant réellement payé : mandats uniques en statut Payé ou Réglé.
     *
     * @param  Collection<int, Mouvement>  $mouvements
     */
    public static function montantPayeTotal(Collection $mouvements): float
    {
        $mandats = self::dedupeForCount(
            self::filterMandats(self::dedupeRows($mouvements))->filter(
                fn (Mouvement $m) => ! StatutNormalizer::isExcluded($m->statut, $m->statut_code)
            )
        );

        return (float) $mandats
            ->filter(function (Mouvement $m) {
                $statut = StatutNormalizer::normalize($m->statut, $m->statut_code) ?? '';

                return in_array($statut, ['Payé', 'Réglé'], true);
            })
            ->sum(function (Mouvement $m) {
                $paye = $m->montant_paye !== null ? abs((float) $m->montant_paye) : null;

                return $paye ?? abs((float) $m->montant);
            });
    }

    /**
     * KPIs mandats pour la hiérarchie (exécutif, performance régions).
     *
     * @param  Collection<int, Mouvement>  $mouvements
     * @return array<string, float|int>
     */
    public static function computeHierarchyStats(Collection $mouvements): array
    {
        $financial = self::financialTotals($mouvements);
        $mandats = self::mandatsForStats($mouvements);
        $statut = fn (Mouvement $m): string => StatutNormalizer::normalize($m->statut, $m->statut_code) ?? '';

        $mandatsTotal = $mandats->count();
        $mandatsRejetes = $mandats->filter(fn (Mouvement $m) => str_contains($statut($m), 'Rejet'))->count();
        $mandatsAdmis = $mandats->filter(fn (Mouvement $m) => $statut($m) === 'Admis')->count();
        $mandatsPayes = $mandats->filter(fn (Mouvement $m) => in_array($statut($m), ['Payé', 'Réglé'], true))->count();

        $montantPaye = (float) $mandats
            ->filter(fn (Mouvement $m) => in_array($statut($m), ['Payé', 'Réglé'], true))
            ->sum(fn (Mouvement $m) => StatutNormalizer::montantForStatut($m));

        $montantTotal = $financial['total_ordonnance'];

        return [
            'mandats_total' => $mandatsTotal,
            'mandats_rejetes' => $mandatsRejetes,
            'mandats_admis' => $mandatsAdmis,
            'mandats_payes' => $mandatsPayes,
            'ordonnance_montant' => $financial['total_ordonnance'],
            'recouvrements_montant' => $financial['total_recouvrements_4121'],
            'montant_paye' => $financial['total_montant_paye'],
            'taux_rejet' => $mandatsTotal > 0 ? round(($mandatsRejetes / $mandatsTotal) * 100, 1) : 0.0,
            'taux_execution' => $montantTotal > 0 ? round(($montantPaye / $montantTotal) * 100, 1) : 0.0,
        ];
    }

    /**
     * Backlog workflow: Admis, autres non payés, cumul hors rejeté.
     *
     * @param  Collection<int, Mouvement>  $mouvements
     * @return array{
     *     admis: array{count: int, montant: float},
     *     autres_non_payes: array{count: int, montant: float},
     *     total_hors_rejet: array{count: int, montant: float}
     * }
     */
    public static function workflowBacklog(Collection $mouvements): array
    {
        // Même base que le tableau "Statuts des mandats" :
        // lignes NAV groupées par statut normalisé, puis agrégées en buckets workflow.
        $statutRows = collect(self::parStatut($mouvements));

        $admis = $statutRows->filter(fn (array $row) => ($row['statut'] ?? '') === 'Admis')->values();
        $autresNonPayes = $statutRows->filter(function (array $row) {
            $label = (string) ($row['statut'] ?? '');

            if ($label === '' || $label === 'Admis') {
                return false;
            }

            if (str_contains($label, 'Rejet')) {
                return false;
            }

            return ! in_array($label, ['Payé', 'Réglé'], true);
        })->values();

        return [
            'admis' => self::workflowBucket($admis),
            'autres_non_payes' => self::workflowBucket($autresNonPayes),
            'total_hors_rejet' => self::workflowBucket($admis->concat($autresNonPayes)->values()),
        ];
    }

    /**
     * Indicateurs workflow avancés pour le dashboard régional.
     *
     * @param  Collection<int, Mouvement>  $mouvements
     * @return array<string, mixed>
     */
    public static function workflowInsights(Collection $mouvements, ?string $referenceDate = null): array
    {
        $reference = $referenceDate ? Carbon::parse($referenceDate)->startOfDay() : now()->startOfDay();
        $histories = self::workflowHistories($mouvements);
        $currentMandats = self::currentWorkflowMandats($mouvements);

        return [
            'temps_par_statut' => self::workflowDurationsByStatus($histories, $reference),
            'conversions' => [
                self::transitionMetric(
                    $histories,
                    'transmis_vers_vise',
                    'Transmis -> Vise',
                    'Transmis',
                    ['Visé', 'Précompté', 'Vérifié', 'Admis', 'Proposé au paiement', 'Payé', 'Réglé'],
                ),
                self::transitionMetric(
                    $histories,
                    'vise_vers_admis',
                    'Vise -> Admis',
                    'Visé',
                    ['Précompté', 'Vérifié', 'Admis', 'Proposé au paiement', 'Payé', 'Réglé'],
                ),
                self::transitionMetric(
                    $histories,
                    'admis_vers_paye',
                    'Admis -> Paye/Regle',
                    'Admis',
                    ['Payé', 'Réglé'],
                ),
            ],
            'reprise_rejets' => self::recoveryAfterReject($histories),
            'immobilises_par_statut' => self::immobilizedByStatus($currentMandats),
            'aging_admis' => self::admisAging($currentMandats, $reference),
        ];
    }

    /**
     * Mouvements issus de v_dashboard_mandats (codes 0/1/2 ou libellés équivalents).
     *
     * @param  Collection<int, Mouvement>  $mouvements
     * @return Collection<int, Mouvement>
     */
    public static function filterMandats(Collection $mouvements): Collection
    {
        return $mouvements->filter(fn (Mouvement $m) => self::resolveTypeCode($m) !== null);
    }

    /**
     * Compte au niveau mandat (numéro + type), aligné NAV / COUNT(DISTINCT numero_mandat).
     * La vue v_dashboard_mandats « lignes » expose plusieurs enregistrements NAV par mandat
     * (historique de statuts) : on conserve la ligne la plus récente.
     *
     * @param  Collection<int, Mouvement>  $mouvements
     * @return Collection<int, Mouvement>
     */
    public static function dedupeForCount(Collection $mouvements): Collection
    {
        return $mouvements
            ->groupBy(fn (Mouvement $m) => self::mandatKey($m))
            ->map(function (Collection $group) {
                return $group->sortByDesc(function (Mouvement $m) {
                    $date = $m->date_mouvement?->format('Y-m-d') ?? '0000-01-01';
                    $pushId = (string) ($m->regional_id ?? '');

                    return $date.'|'.$pushId;
                })->first();
            })
            ->values();
    }

    private static function mandatKey(Mouvement $m): string
    {
        $code = self::resolveTypeCode($m) ?? '';
        $numero = (string) ($m->source_numero_mandat ?: $m->regional_id);

        return implode('|', [$numero, $code]);
    }

    /**
     * Lignes mandats alignées écran NAV (1 ligne = 1 enregistrement SAN$Mandat).
     *
     * @param  Collection<int, Mouvement>  $mouvements
     * @return Collection<int, Mouvement>
     */
    public static function navMandatLines(Collection $mouvements): Collection
    {
        return self::filterMandats(self::dedupeRows($mouvements))->filter(
            fn (Mouvement $m) => self::isNavLineCountable($m)
        );
    }

    /**
     * @param  Collection<int, Mouvement>  $mouvements
     * @return array<int, array{code: string, libelle: string, count: int, montant: float}>
     */
    public static function parType(Collection $mouvements): array
    {
        // Comptage par ligne NAV (écran Mandats), référence métier AICE.
        $rows = self::navMandatLines($mouvements);
        $result = [];

        foreach (self::TYPE_LABELS as $code => $label) {
            $code = (string) $code;
            $subset = $rows->filter(fn (Mouvement $m) => self::resolveTypeCode($m) === $code);
            $result[] = [
                'code' => $code,
                'libelle' => $label,
                'count' => $subset->count(),
                'montant' => (float) $subset->sum('montant'),
            ];
        }

        return $result;
    }

    /**
     * @param  Collection<int, Mouvement>  $mouvements
     * @return array<int, array{statut: string, count: int, montant: float}>
     */
    public static function parStatut(Collection $mouvements): array
    {
        // Comptage par ligne NAV (écran Mandats), pas par dédup (numero + type).
        $rows = self::navMandatLines($mouvements);

        return $rows
            ->groupBy(fn (Mouvement $m) => StatutNormalizer::normalize($m->statut, $m->statut_code) ?? 'Non renseigné')
            ->map(fn (Collection $group, string $statut) => [
                'statut' => $statut,
                'count' => $group->count(),
                'montant' => (float) $group->sum(fn (Mouvement $m) => (float) $m->montant),
            ])
            ->values()
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Collection<int, Mouvement>>  $histories
     * @return array<int, array{statut: string, count: int, average_days: float, max_days: int}>
     */
    private static function workflowDurationsByStatus(Collection $histories, Carbon $reference): array
    {
        $aggregates = [];

        foreach ($histories as $history) {
            $rows = $history->values();

            for ($index = 0; $index < $rows->count(); $index++) {
                /** @var Mouvement $row */
                $row = $rows[$index];
                $statut = self::normalizedStatus($row);
                $start = self::movementDate($row);
                $end = $index < $rows->count() - 1 ? self::movementDate($rows[$index + 1]) : $reference;

                if ($statut === null || $start === null || $end === null) {
                    continue;
                }

                $days = max(0, $start->diffInDays($end));

                if (!isset($aggregates[$statut])) {
                    $aggregates[$statut] = ['statut' => $statut, 'count' => 0, 'total_days' => 0.0, 'max_days' => 0];
                }

                $aggregates[$statut]['count']++;
                $aggregates[$statut]['total_days'] += $days;
                $aggregates[$statut]['max_days'] = max($aggregates[$statut]['max_days'], $days);
            }
        }

        return collect($aggregates)
            ->map(fn (array $row) => [
                'statut' => $row['statut'],
                'count' => $row['count'],
                'average_days' => $row['count'] > 0 ? round($row['total_days'] / $row['count'], 1) : 0.0,
                'max_days' => $row['max_days'],
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Collection<int, Mouvement>>  $histories
     * @param  array<int, string>  $targets
     * @return array{key: string, label: string, base_count: int, converted_count: int, taux_pct: float}
     */
    private static function transitionMetric(
        Collection $histories,
        string $key,
        string $label,
        string $source,
        array $targets,
    ): array {
        $baseCount = 0;
        $convertedCount = 0;

        foreach ($histories as $history) {
            $labels = $history
                ->map(fn (Mouvement $m) => self::normalizedStatus($m))
                ->filter()
                ->values();

            $sourceIndex = $labels->search($source);
            if ($sourceIndex === false) {
                continue;
            }

            $baseCount++;

            $hasTarget = $labels
                ->slice($sourceIndex + 1)
                ->contains(fn (?string $status) => in_array($status, $targets, true));

            if ($hasTarget) {
                $convertedCount++;
            }
        }

        return [
            'key' => $key,
            'label' => $label,
            'base_count' => $baseCount,
            'converted_count' => $convertedCount,
            'taux_pct' => $baseCount > 0 ? round(($convertedCount / $baseCount) * 100, 1) : 0.0,
        ];
    }

    /**
     * @param  Collection<int, Collection<int, Mouvement>>  $histories
     * @return array{rejetes_count: int, repris_count: int, taux_pct: float}
     */
    private static function recoveryAfterReject(Collection $histories): array
    {
        $rejectedCount = 0;
        $recoveredCount = 0;

        foreach ($histories as $history) {
            $labels = $history
                ->map(fn (Mouvement $m) => self::normalizedStatus($m))
                ->filter()
                ->values();

            $rejectIndexes = $labels
                ->map(fn (?string $status, int $index) => str_contains((string) $status, 'Rejet') ? $index : null)
                ->filter(fn ($value) => $value !== null)
                ->values();

            if ($rejectIndexes->isEmpty()) {
                continue;
            }

            $rejectedCount++;
            $lastRejectIndex = (int) $rejectIndexes->last();
            $recovered = $labels
                ->slice($lastRejectIndex + 1)
                ->contains(fn (?string $status) => $status !== null && ! str_contains($status, 'Rejet'));

            if ($recovered) {
                $recoveredCount++;
            }
        }

        return [
            'rejetes_count' => $rejectedCount,
            'repris_count' => $recoveredCount,
            'taux_pct' => $rejectedCount > 0 ? round(($recoveredCount / $rejectedCount) * 100, 1) : 0.0,
        ];
    }

    /**
     * @param  Collection<int, Mouvement>  $currentMandats
     * @return array<int, array{statut: string, count: int, montant: float}>
     */
    private static function immobilizedByStatus(Collection $currentMandats): array
    {
        return $currentMandats
            ->map(function (Mouvement $m) {
                $statut = self::normalizedStatus($m);

                if ($statut === null || in_array($statut, ['Payé', 'Réglé'], true)) {
                    return null;
                }

                return [
                    'statut' => $statut,
                    'count' => 1,
                    'montant' => (float) StatutNormalizer::montantForStatut($m),
                ];
            })
            ->filter()
            ->groupBy('statut')
            ->map(fn (Collection $group, string $statut) => [
                'statut' => $statut,
                'count' => (int) $group->sum('count'),
                'montant' => (float) $group->sum('montant'),
            ])
            ->sortByDesc('montant')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Mouvement>  $currentMandats
     * @return array<string, mixed>
     */
    private static function admisAging(Collection $currentMandats, Carbon $reference): array
    {
        $admis = $currentMandats
            ->filter(fn (Mouvement $m) => self::normalizedStatus($m) === 'Admis')
            ->values();

        $ages = $admis
            ->map(function (Mouvement $m) use ($reference) {
                $date = self::movementDate($m);

                return $date ? max(0, $date->diffInDays($reference)) : null;
            })
            ->filter(fn ($days) => $days !== null)
            ->values();

        return [
            'count' => $admis->count(),
            'montant' => (float) $admis->sum(fn (Mouvement $m) => StatutNormalizer::montantForStatut($m)),
            'average_days' => $ages->isNotEmpty() ? round((float) $ages->avg(), 1) : 0.0,
            'max_days' => $ages->isNotEmpty() ? (int) $ages->max() : 0,
            'buckets' => [
                ['label' => '0-7 jours', 'count' => $ages->filter(fn (int $days) => $days <= 7)->count()],
                ['label' => '8-15 jours', 'count' => $ages->filter(fn (int $days) => $days >= 8 && $days <= 15)->count()],
                ['label' => '16-30 jours', 'count' => $ages->filter(fn (int $days) => $days >= 16 && $days <= 30)->count()],
                ['label' => '> 30 jours', 'count' => $ages->filter(fn (int $days) => $days > 30)->count()],
            ],
        ];
    }

    /**
     * @param  Collection<int, Mouvement>  $mouvements
     * @return Collection<int, Collection<int, Mouvement>>
     */
    private static function workflowHistories(Collection $mouvements): Collection
    {
        return self::filterMandats(self::dedupeRows($mouvements))
            ->filter(fn (Mouvement $m) => ! StatutNormalizer::isExcluded($m->statut, $m->statut_code))
            ->groupBy(fn (Mouvement $m) => self::mandatKey($m))
            ->map(fn (Collection $group) => $group->sortBy(fn (Mouvement $m) => self::movementSortKey($m))->values())
            ->values();
    }

    /**
     * @param  Collection<int, Mouvement>  $mouvements
     * @return Collection<int, Mouvement>
     */
    private static function currentWorkflowMandats(Collection $mouvements): Collection
    {
        return self::dedupeForCount(
            self::filterMandats(self::dedupeRows($mouvements))->filter(
                fn (Mouvement $m) => ! StatutNormalizer::isExcluded($m->statut, $m->statut_code)
            )
        )->values();
    }

    /**
     * Ligne comptable comme l'écran NAV (inclut statut vide, exclut DIAG/TEST).
     */
    private static function isNavLineCountable(Mouvement $m): bool
    {
        $label = StatutNormalizer::normalize($m->statut, $m->statut_code);
        if ($label === null) {
            return true;
        }

        return ! in_array(mb_strtoupper($label), ['DIAG', 'TEST', 'N/A'], true);
    }

    private static function resolveTypeCode(Mouvement $m): ?string
    {
        // Priorité au libellé (aligné AICE / écran Mandats), puis au code MP_TYPE.
        $libelle = self::normalizeTypeLibelle($m->type_mandat_libelle);
        if ($libelle !== null) {
            $byLabel = array_flip(self::TYPE_LABELS);

            return (string) $byLabel[$libelle];
        }

        return self::normalizeTypeCode($m->type_mandat);
    }

    private static function normalizeTypeCode(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $code = trim((string) $raw);
        if (in_array($code, ['0', '1', '2'], true)) {
            return $code;
        }

        if (is_numeric($code)) {
            $normalized = (string) (int) $code;
            if (in_array($normalized, ['0', '1', '2'], true)) {
                return $normalized;
            }
        }

        return null;
    }

    private static function normalizeTypeLibelle(?string $libelle): ?string
    {
        if ($libelle === null || trim($libelle) === '') {
            return null;
        }

        if (in_array($libelle, self::TYPE_LABELS, true)) {
            return $libelle;
        }

        $aliases = [
            'matériel' => 'Matériel',
            'materiel' => 'Matériel',
            'matériels' => 'Matériel',
            'materiels' => 'Matériel',
            'salaire' => 'Salaire',
            'salaires' => 'Salaire',
            'reversement' => 'Reversement',
            'reversements' => 'Reversement',
        ];

        return $aliases[mb_strtolower(trim($libelle))] ?? null;
    }

    /**
     * @param  Collection<int, array{statut?: string, count?: int, montant?: float|int}>  $statusRows
     * @return array{count: int, montant: float}
     */
    private static function workflowBucket(Collection $statusRows): array
    {
        return [
            'count' => (int) $statusRows->sum(fn (array $row) => (int) ($row['count'] ?? 0)),
            'montant' => (float) $statusRows->sum(fn (array $row) => (float) ($row['montant'] ?? 0)),
        ];
    }

    private static function normalizedStatus(Mouvement $m): ?string
    {
        return StatutNormalizer::normalize($m->statut, $m->statut_code);
    }

    private static function movementSortKey(Mouvement $m): string
    {
        return sprintf(
            '%s|%010d|%s',
            self::movementDate($m)?->format('Y-m-d') ?? '0000-01-01',
            (int) ($m->id ?? 0),
            (string) ($m->regional_id ?? ''),
        );
    }

    private static function movementDate(Mouvement $m): ?Carbon
    {
        if ($m->date_mouvement !== null) {
            return Carbon::parse($m->date_mouvement)->startOfDay();
        }

        if ($m->annee !== null) {
            return Carbon::create((int) $m->annee, (int) ($m->mois ?: 1), 1)->startOfDay();
        }

        return null;
    }
}
