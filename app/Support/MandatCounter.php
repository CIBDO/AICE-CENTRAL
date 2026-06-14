<?php

namespace App\Support;

use App\Models\Mouvement;
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

        $depenseMandats = self::dedupeForCount(
            self::filterMandats($rows)->filter(
                fn (Mouvement $m) => ! StatutNormalizer::isExcluded($m->statut, $m->statut_code)
            )
        );
        $ordonnance = (float) $depenseMandats->sum(fn (Mouvement $m) => (float) $m->montant);
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

        $montantTotal = (float) $mandats->sum(fn (Mouvement $m) => StatutNormalizer::montantForStatut($m));

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
     * Compte au niveau mandat (numéro + type + date), en évitant les doublons entre pushs.
     *
     * @param  Collection<int, Mouvement>  $mouvements
     * @return Collection<int, Mouvement>
     */
    public static function dedupeForCount(Collection $mouvements): Collection
    {
        return $mouvements->unique(function (Mouvement $m) {
            $code = self::resolveTypeCode($m) ?? '';
            $numero = $m->source_numero_mandat ?: $m->regional_id;
            $date = $m->date_mouvement?->format('Y-m-d') ?? 'null';

            return implode('|', [$numero, $code, $date]);
        });
    }

    /**
     * @param  Collection<int, Mouvement>  $mouvements
     * @return array<int, array{code: string, libelle: string, count: int, montant: float}>
     */
    public static function parType(Collection $mouvements): array
    {
        $deduped = self::dedupeForCount(self::filterMandats(self::dedupeRows($mouvements)));
        $result = [];

        foreach (self::TYPE_LABELS as $code => $label) {
            $code = (string) $code;
            $subset = $deduped->filter(fn (Mouvement $m) => self::resolveTypeCode($m) === $code);
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
        $deduped = self::dedupeForCount(
            self::filterMandats(self::dedupeRows($mouvements))->filter(
                fn (Mouvement $m) => ! StatutNormalizer::isExcluded($m->statut, $m->statut_code)
            )
        );

        return $deduped
            ->groupBy(fn (Mouvement $m) => StatutNormalizer::normalize($m->statut, $m->statut_code) ?? 'Non renseigné')
            ->map(fn (Collection $group, string $statut) => [
                'statut' => $statut,
                'count' => $group->count(),
                'montant' => (float) $group->sum(fn (Mouvement $m) => StatutNormalizer::montantForStatut($m)),
            ])
            ->values()
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private static function resolveTypeCode(Mouvement $m): ?string
    {
        $code = trim((string) ($m->type_mandat ?? ''));
        if (in_array($code, ['0', '1', '2'], true)) {
            return $code;
        }

        $libelle = $m->type_mandat_libelle;
        if ($libelle === null || $libelle === '') {
            return null;
        }

        $byLabel = array_flip(self::TYPE_LABELS);

        return isset($byLabel[$libelle]) ? (string) $byLabel[$libelle] : null;
    }
}
