<?php

namespace App\Support;

use App\Models\Dashboard;

final class DashboardKpis
{
    /**
     * @return array<string, float>
     */
    public static function empty(): array
    {
        return [
            'total_ordonnance' => 0.0,
            'total_recouvrements_4121' => 0.0,
            'total_montant_paye' => 0.0,
            'tresorerie_reelle' => 0.0,
            'solde' => 0.0,
        ];
    }

    /**
     * @return array<string, float>
     */
    public static function fromDashboard(Dashboard $dashboard): array
    {
        return [
            'total_ordonnance' => (float) $dashboard->total_ordonnance,
            'total_recouvrements_4121' => (float) $dashboard->total_recouvrements_4121,
            'total_montant_paye' => (float) $dashboard->total_montant_paye,
            'tresorerie_reelle' => (float) $dashboard->tresorerie_reelle,
            'solde' => (float) $dashboard->solde,
        ];
    }

    /**
     * @param  array<string, mixed>  $financial
     * @return array<string, float>
     */
    public static function fromFinancialTotals(array $financial, float $tresorerieReelle = 0.0): array
    {
        $ordonnance = (float) ($financial['total_ordonnance'] ?? $financial['depenses'] ?? 0);
        $recouvrements = (float) ($financial['total_recouvrements_4121'] ?? $financial['recettes'] ?? 0);
        $paye = (float) ($financial['total_montant_paye'] ?? 0);

        return [
            'total_ordonnance' => $ordonnance,
            'total_recouvrements_4121' => $recouvrements,
            'total_montant_paye' => $paye,
            'tresorerie_reelle' => $tresorerieReelle,
            'solde' => $recouvrements - $ordonnance,
        ];
    }

    /**
     * Accepte les clés historiques (total_depenses, total_recettes, encaisse).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, float>
     */
    public static function normalizeIncomingPayload(array $payload): array
    {
        $ordonnance = (float) ($payload['total_ordonnance'] ?? $payload['total_depenses'] ?? 0);
        $recouvrements = (float) ($payload['total_recouvrements_4121'] ?? $payload['total_recettes'] ?? 0);
        $paye = (float) ($payload['total_montant_paye'] ?? 0);
        $tresorerie = (float) ($payload['tresorerie_reelle'] ?? $payload['encaisse'] ?? 0);
        $solde = array_key_exists('solde', $payload)
            ? (float) $payload['solde']
            : $recouvrements - $ordonnance;

        return [
            'total_ordonnance' => $ordonnance,
            'total_recouvrements_4121' => $recouvrements,
            'total_montant_paye' => $paye,
            'tresorerie_reelle' => $tresorerie,
            'solde' => $solde,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $recettes
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeRecettesClients(array $recettes): array
    {
        return array_values(array_map(function (array $recette) {
            $entryNo = trim((string) ($recette['source_no'] ?? ''));
            $clientNo = trim((string) ($recette['client_no'] ?? ''));
            if ($clientNo === '' || strtoupper($clientNo) === 'UNKNOWN') {
                $clientNo = $entryNo !== '' ? 'NR-' . $entryNo : 'NON-RENSEIGNE';
            }

            $clientName = trim((string) ($recette['client_name'] ?? $recette['nom_client'] ?? ''));
            if ($clientName === '') {
                $libelle = trim((string) ($recette['description'] ?? ''));
                $clientName = $libelle !== '' ? $libelle : "Client {$clientNo}";
            }

            $recette['client_no'] = $clientNo;
            $recette['client_name'] = $clientName;
            $recette['nom_client'] = $clientName;

            return $recette;
        }, $recettes));
    }
}
