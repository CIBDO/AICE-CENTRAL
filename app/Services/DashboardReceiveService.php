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

            $mouvementsProcessed = 0;
            foreach ($payload['mouvements'] as $mouvementData) {
                $this->upsertMouvement($dashboard, $mouvementData, $payload);
                $mouvementsProcessed++;
            }

            $banquesProcessed = 0;
            foreach ($payload['banques'] ?? [] as $banqueData) {
                $this->upsertBanque($dashboard, $banqueData);
                $banquesProcessed++;
            }

            $recettesProcessed = 0;
            foreach ($payload['recettes_clients'] ?? [] as $recetteData) {
                $this->upsertRecetteClient($dashboard, $recetteData);
                $recettesProcessed++;
            }

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
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $dashboardPayload
     */
    private function upsertMouvement(Dashboard $dashboard, array $data, array $dashboardPayload): void
    {
        [$annee, $mois] = $this->resolvePeriod(
            $data['date_mouvement'] ?? null,
            $data['annee'] ?? null,
            $data['mois'] ?? null,
            $dashboardPayload['annee'] ?? null,
            $dashboardPayload['mois'] ?? null,
        );

        Mouvement::updateOrCreate(
            [
                'dashboard_id' => $dashboard->id,
                'regional_id' => $data['regional_id'],
            ],
            [
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
            ]
        );
    }

    /** @param  array<string, mixed>  $data */
    private function upsertBanque(Dashboard $dashboard, array $data): void
    {
        $regionalId = $data['regional_id'] ?? $this->deriveRegionalId('BNQ', [
            $dashboard->regional_id,
            $data['numero_compte'] ?? '',
            $data['reference'] ?? '',
            $data['entry_no'] ?? '',
            $data['date_mouvement'] ?? '',
        ]);

        BanquePush::updateOrCreate(
            [
                'dashboard_id' => $dashboard->id,
                'regional_id' => $regionalId,
            ],
            [
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
            ]
        );
    }

    /** @param  array<string, mixed>  $data */
    private function upsertRecetteClient(Dashboard $dashboard, array $data): void
    {
        $regionalId = $data['regional_id'] ?? $this->deriveRegionalId('RCT', [
            $dashboard->regional_id,
            $data['client_no'] ?? '',
            $data['source_no'] ?? '',
            $data['date_posting'] ?? '',
            $data['montant'] ?? '',
        ]);

        RecetteClientPush::updateOrCreate(
            [
                'dashboard_id' => $dashboard->id,
                'regional_id' => $regionalId,
            ],
            [
                'client_no' => $data['client_no'],
                'client_name' => $data['client_name'],
                'gl_account' => $data['gl_account'] ?? null,
                'date_posting' => $data['date_posting'] ?? null,
                'montant' => $data['montant'] ?? 0,
                'description' => $data['description'] ?? null,
                'source_no' => $data['source_no'] ?? null,
                'exercice' => $data['exercice'] ?? null,
            ]
        );
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
