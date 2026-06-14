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
        $deduped = self::dedupeForCount(self::filterMandats($mouvements));
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
