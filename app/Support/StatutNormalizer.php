<?php

namespace App\Support;

use App\Models\Mouvement;

class StatutNormalizer
{
    /** @var array<string, string> */
    public const CODE_TO_LABEL = [
        'S00' => 'Transmis',
        'S01' => 'Réceptionné',
        'S03' => 'Visé',
        'S04' => 'Précompté',
        'S29' => 'Réglé',
        'S30' => 'Admis',
        'S31' => 'Vérifié',
        'S32' => 'Proposé au paiement',
        'S92' => 'Payé',
    ];

    /** Statuts de test / invalides exclus des statistiques dashboard. */
    private const EXCLUDED = [
        'DIAG',
        'TEST',
        'N/A',
    ];

    public static function normalize(?string $statut, ?string $statutCode = null): ?string
    {
        $code = strtoupper(trim((string) $statutCode));
        if ($code !== '' && isset(self::CODE_TO_LABEL[$code])) {
            return self::CODE_TO_LABEL[$code];
        }

        $label = trim((string) $statut);
        if ($label === '') {
            return null;
        }

        if (isset(self::CODE_TO_LABEL[strtoupper($label)])) {
            return self::CODE_TO_LABEL[strtoupper($label)];
        }

        $upper = mb_strtoupper($label);
        if (in_array($upper, ['PAYE', 'PAYÉ'], true)) {
            return 'Payé';
        }
        if (in_array($upper, ['REGLE', 'RÉGLÉ'], true)) {
            return 'Réglé';
        }
        if ($upper === 'ADMIS') {
            return 'Admis';
        }
        if (str_starts_with($upper, 'REJET')) {
            return $label;
        }

        return $label;
    }

    public static function isExcluded(?string $statut, ?string $statutCode = null): bool
    {
        $label = self::normalize($statut, $statutCode);
        if ($label === null) {
            return true;
        }

        return in_array(mb_strtoupper($label), self::EXCLUDED, true);
    }

    /**
     * Montant affiché par statut (aligné AICE : Montant_Paye vs Solde_a_Paye).
     */
    public static function montantForStatut(Mouvement $m): float
    {
        $statut = self::normalize($m->statut, $m->statut_code) ?? '';
        $montant = abs((float) $m->montant);
        $paye = $m->montant_paye !== null ? abs((float) $m->montant_paye) : null;
        $solde = $m->solde_a_payer !== null ? abs((float) $m->solde_a_payer) : null;

        if (in_array($statut, ['Payé', 'Réglé', 'Précompté'], true)) {
            return $paye ?? $montant;
        }

        if (in_array($statut, ['Admis', 'Proposé au paiement', 'Vérifié'], true)) {
            return $solde ?? $montant;
        }

        return $montant;
    }
}
