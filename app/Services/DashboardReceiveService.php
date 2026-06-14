<?php

namespace App\Services;

use App\Models\BanquePush;
use App\Models\Dashboard;
use App\Models\Mouvement;
use App\Models\RecetteClientPush;
use App\Models\Region;
use App\Support\DashboardKpis;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardReceiveService
{
    private const UPSERT_BATCH = 200;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handle(int $regionId, array $payload): array
    {
        return DB::transaction(function () use ($regionId, $payload) {
            $region = Region::findOrFail($regionId);

            $kpis = DashboardKpis::normalizeIncomingPayload($payload);

            $dashboard = Dashboard::updateOrCreate(
                [
                    'region_id' => $regionId,
                    'regional_id' => $payload['regional_id'],
                ],
                [
                    'local_id' => $payload['local_id'],
                    'total_ordonnance' => $kpis['total_ordonnance'],
                    'total_recouvrements_4121' => $kpis['total_recouvrements_4121'],
                    'total_montant_paye' => $kpis['total_montant_paye'],
                    'solde' => $kpis['solde'],
                    'tresorerie_reelle' => $kpis['tresorerie_reelle'],
                    'annee' => $payload['annee'] ?? null,
                    'mois' => $payload['mois'] ?? null,
                    'date_debut' => $payload['date_debut'] ?? null,
                    'date_fin' => $payload['date_fin'] ?? null,
                ]
            );

            $mouvementsProcessed = $this->bulkUpsertMouvements(
                $dashboard,
                $payload['mouvements'] ?? [],
                $payload,
            );

            $banquesProcessed = $this->bulkUpsertBanques($dashboard, $payload['banques'] ?? []);

            $recettesProcessed = $this->bulkUpsertRecettes($dashboard, $payload['recettes_clients'] ?? []);

            $region->updateLastConnection();

            return [
                'status' => 'OK',
                'regional_id' => $payload['regional_id'],
                'local_id' => $payload['local_id'],
                'dashboard_id' => $dashboard->id,
                'mouvements' => [
                    'processed' => $mouvementsProcessed,
                ],
                'banques' => [
                    'processed' => $banquesProcessed,
                ],
                'recettes_clients' => [
                    'processed' => $recettesProcessed,
                ],
            ];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $mouvements
     * @param  array<string, mixed>  $dashboardPayload
     */
    private function bulkUpsertMouvements(Dashboard $dashboard, array $mouvements, array $dashboardPayload): int
    {
        if ($mouvements === []) {
            return 0;
        }

        $now = now();
        $rows = [];

        foreach ($mouvements as $data) {
            [$annee, $mois] = $this->resolvePeriod(
                $data['date_mouvement'] ?? null,
                $data['annee'] ?? null,
                $data['mois'] ?? null,
                $dashboardPayload['annee'] ?? null,
                $dashboardPayload['mois'] ?? null,
            );

            $rows[] = [
                'dashboard_id' => $dashboard->id,
                'regional_id' => $data['regional_id'],
                'libelle' => $data['libelle'],
                'montant' => $data['montant'],
                'type' => $data['type'],
                'date_mouvement' => $data['date_mouvement'] ?? null,
                'annee' => $annee,
                'mois' => $mois,
                'programme' => $data['programme'] ?? null,
                'nature' => $data['nature'] ?? null,
                'code_programme' => $data['code_programme'] ?? null,
                'chapitre' => $data['chapitre'] ?? null,
                'nature_ce' => $data['nature_ce'] ?? null,
                'statut' => $data['statut'] ?? null,
                'statut_code' => $data['statut_code'] ?? null,
                'montant_paye' => $data['montant_paye'] ?? null,
                'solde_a_payer' => $data['solde_a_payer'] ?? null,
                'beneficiaire' => $data['beneficiaire'] ?? null,
                'source_numero_mandat' => $data['source_numero_mandat'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'type_mandat' => $data['type_mandat'] ?? null,
                'type_mandat_libelle' => $data['type_mandat_libelle'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $updateColumns = [
            'libelle', 'montant', 'type', 'date_mouvement', 'annee', 'mois',
            'programme', 'nature', 'code_programme', 'chapitre', 'nature_ce',
            'statut', 'statut_code', 'montant_paye', 'solde_a_payer', 'beneficiaire',
            'source_numero_mandat', 'source_id', 'type_mandat', 'type_mandat_libelle',
            'updated_at',
        ];

        foreach (array_chunk($rows, self::UPSERT_BATCH) as $chunk) {
            Mouvement::upsert($chunk, ['dashboard_id', 'regional_id'], $updateColumns);
        }

        return count($rows);
    }

    /** @param  array<int, array<string, mixed>>  $banques */
    private function bulkUpsertBanques(Dashboard $dashboard, array $banques): int
    {
        if ($banques === []) {
            return 0;
        }

        $now = now();
        $rows = [];

        foreach ($banques as $data) {
            $rows[] = [
                'dashboard_id' => $dashboard->id,
                'regional_id' => $data['regional_id'] ?? $this->deriveRegionalId('BNQ', [
                    $dashboard->regional_id,
                    $data['numero_compte'] ?? '',
                    $data['reference'] ?? '',
                    $data['entry_no'] ?? '',
                    $data['date_mouvement'] ?? '',
                ]),
                'numero_compte' => $data['numero_compte'],
                'libelle' => $data['libelle'],
                'date_mouvement' => $data['date_mouvement'] ?? null,
                'debit' => $data['debit'] ?? 0,
                'credit' => $data['credit'] ?? 0,
                'solde' => $data['solde'] ?? 0,
                'reference' => $data['reference'] ?? null,
                'entry_no' => $data['entry_no'] ?? null,
                'exercice' => $data['exercice'] ?? null,
                'type_document' => $data['type_document'] ?? null,
                'description' => $data['description'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $updateColumns = [
            'numero_compte', 'libelle', 'date_mouvement', 'debit', 'credit', 'solde',
            'reference', 'entry_no', 'exercice', 'type_document', 'description', 'updated_at',
        ];

        foreach (array_chunk($rows, self::UPSERT_BATCH) as $chunk) {
            BanquePush::upsert($chunk, ['dashboard_id', 'regional_id'], $updateColumns);
        }

        return count($rows);
    }

    /** @param  array<int, array<string, mixed>>  $recettes */
    private function bulkUpsertRecettes(Dashboard $dashboard, array $recettes): int
    {
        if ($recettes === []) {
            return 0;
        }

        $now = now();
        $rows = [];

        foreach ($recettes as $data) {
            $rows[] = [
                'dashboard_id' => $dashboard->id,
                'regional_id' => $data['regional_id'] ?? $this->deriveRegionalId('RCT', [
                    $dashboard->regional_id,
                    $data['client_no'] ?? '',
                    $data['source_no'] ?? '',
                    $data['date_posting'] ?? '',
                    $data['montant'] ?? '',
                ]),
                'client_no' => $data['client_no'],
                'client_name' => $data['client_name'],
                'gl_account' => $data['gl_account'] ?? null,
                'date_posting' => $data['date_posting'] ?? null,
                'montant' => $data['montant'] ?? 0,
                'description' => $data['description'] ?? null,
                'source_no' => $data['source_no'] ?? null,
                'exercice' => $data['exercice'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $updateColumns = [
            'client_no', 'client_name', 'gl_account', 'date_posting', 'montant',
            'description', 'source_no', 'exercice', 'updated_at',
        ];

        foreach (array_chunk($rows, self::UPSERT_BATCH) as $chunk) {
            RecetteClientPush::upsert($chunk, ['dashboard_id', 'regional_id'], $updateColumns);
        }

        return count($rows);
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function resolvePeriod(
        ?string $dateMouvement,
        ?int $annee,
        ?int $mois,
        ?int $fallbackAnnee,
        ?int $fallbackMois,
    ): array {
        if ($annee !== null && $mois !== null) {
            return [$annee, $mois];
        }

        if ($dateMouvement) {
            $date = Carbon::parse($dateMouvement);

            return [$date->year, $date->month];
        }

        return [$fallbackAnnee, $fallbackMois];
    }

    /** @param  array<int|string|null>  $parts */
    private function deriveRegionalId(string $prefix, array $parts): string
    {
        $payload = implode('|', array_map(static fn ($part) => (string) $part, $parts));

        return $prefix . '-' . substr(hash('sha256', $payload), 0, 32);
    }
}
