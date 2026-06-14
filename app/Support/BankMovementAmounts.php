<?php

namespace App\Support;

/**
 * Montants bancaires NAV → convention État (débit = entrée, crédit = sortie).
 */
class BankMovementAmounts
{
    /**
     * Montants NAV bruts en valeurs positives.
     *
     * @return array{0: float, 1: float} [debit_nav, credit_nav]
     */
    public static function normalize(float $debit, float $credit, ?float $signedAmount = null): array
    {
        if ($debit < 0) {
            $debit = abs($debit);
        }

        if ($credit < 0) {
            $credit = abs($credit);
        }

        if ($debit === 0.0 && $credit === 0.0 && $signedAmount !== null && $signedAmount !== 0.0) {
            if ($signedAmount < 0) {
                $debit = abs($signedAmount);
            } else {
                $credit = $signedAmount;
            }
        }

        return [$debit, $credit];
    }

    /**
     * Inverse le sens banque NAV vers la comptabilité de l'État.
     *
     * @return array{0: float, 1: float} [debit_etat_entree, credit_etat_sortie]
     */
    public static function toStateConvention(float $debitNav, float $creditNav, ?float $signedAmount = null): array
    {
        [$debitNav, $creditNav] = self::normalize($debitNav, $creditNav, $signedAmount);

        return [$creditNav, $debitNav];
    }
}
