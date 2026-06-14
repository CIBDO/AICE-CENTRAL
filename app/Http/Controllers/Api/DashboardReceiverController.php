<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDashboardChunk;
use App\Services\DashboardReceiveService;
use App\Support\DashboardKpis;
use App\Support\DashboardPayloadValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardReceiverController extends Controller
{
    public function __construct(
        private readonly DashboardReceiveService $service,
        private readonly DashboardPayloadValidator $payloadValidator,
    ) {
    }

    /**
     * Reçoit et stocke les données du dashboard d'une région
     *
     * POST /api/v1/receive/dashboard
     *
     * Headers requis:
     * - X-REGION-TOKEN: Token de la région
     *
     * Payload JSON attendu:
     * {
     *   "local_id": "RGD-001",
     *   "total_recettes": 1500000.00,
     *   "total_depenses": 1200000.00,
     *   "solde": 300000.00,
     *   "encaisse": 50000.00,
     *   "mouvements": [
     *     {
     *       "libelle": "Paiement mandat 001",
     *       "montant": 50000.00,
     *       "type": "depense"
     *     }
     *   ]
     * }
     */
    public function receive(Request $request)
    {
        @set_time_limit((int) env('DASHBOARD_RECEIVE_MAX_EXECUTION', 600));

        // Récupérer la région authentifiée depuis le middleware
        // Essayer plusieurs méthodes pour récupérer la région
        $region = $request->get('authenticated_region')
                ?? $request->input('authenticated_region')
                ?? $request->attributes->get('authenticated_region');

        if (!$region) {
            return response()->json([
                'status' => 'error',
                'message' => 'Région non authentifiée',
            ], 401);
        }

        // Logging léger (optionnel) pour diagnostiquer les envois régionaux
        $chunkInfo = $request->input('chunk_info', null);
        Log::info('Réception dashboard (push régional)', [
            'region_code' => $region->code,
            'local_id' => $request->input('local_id'),
            'regional_id' => $request->input('regional_id'),
            'chunk_info' => $chunkInfo,
            'mouvements_count' => is_array($request->input('mouvements')) ? count($request->input('mouvements')) : null,
        ]);

        $payload = $request->all();
        $payload = array_merge($payload, DashboardKpis::normalizeIncomingPayload($payload));
        if (!empty($payload['recettes_clients']) && is_array($payload['recettes_clients'])) {
            $payload['recettes_clients'] = DashboardKpis::normalizeRecettesClients($payload['recettes_clients']);
        }
        $request->merge($payload);

        // Validation (rapide — pas de règles mouvements.* / banques.* sur gros volumes)
        try {
            $validated = $this->payloadValidator->validate($payload);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $errorsArray = $errors;

            Log::warning("Validation échouée pour région {$region->code}", [
                'total_mouvements' => count($request->input('mouvements', [])),
                'total_banques' => count($request->input('banques', [])),
                'total_errors' => count($errorsArray),
                'chunk_info' => $chunkInfo,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Données invalides',
                'errors' => $errorsArray,
                'summary' => [
                    'total_mouvements' => count($request->input('mouvements', [])),
                    'total_errors' => count($errorsArray),
                ],
            ], 422);
        }

        // Sécurité: éviter qu'une région envoie un local_id qui ne correspond pas à son token.
        $normalizeRegionCode = static function (?string $value): string {
            return mb_strtoupper(trim((string) $value));
        };

        if ($normalizeRegionCode($validated['local_id'] ?? '') !== $normalizeRegionCode($region->code)) {
            Log::warning('Mismatch local_id vs région authentifiée', [
                'region_code' => $region->code,
                'local_id' => $validated['local_id'] ?? null,
                'regional_id' => $validated['regional_id'] ?? null,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'local_id ne correspond pas à la région authentifiée par X-REGION-TOKEN. Attendu: ' . $region->code,
            ], 403);
        }

        try {
            $useQueue = filter_var(env('DASHBOARD_RECEIVE_USE_QUEUE', false), FILTER_VALIDATE_BOOLEAN);
            $queueMinSize = (int) env('DASHBOARD_RECEIVE_QUEUE_MIN_SIZE', 250);
            $mouvementsCount = is_array($validated['mouvements'] ?? null) ? count($validated['mouvements']) : 0;

            if ($useQueue && $mouvementsCount >= $queueMinSize) {
                ProcessDashboardChunk::dispatch($region->id, $validated);

                return response()->json([
                    'status' => 'accepted',
                    'message' => 'Chunk reçu et mis en traitement (queue).',
                    'regional_id' => $validated['regional_id'],
                    'local_id' => $validated['local_id'],
                    'chunk_info' => $validated['chunk_info'] ?? null,
                    'mouvements' => [
                        'total' => $mouvementsCount,
                    ],
                ], 202);
            }

            $result = $this->service->handle($region->id, $validated);

            return response()->json($result, 200);

        } catch (\Illuminate\Database\QueryException $e) {
            // Gérer spécifiquement les erreurs de contrainte unique
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'UNIQUE constraint')) {
                Log::warning("Erreur de contrainte unique (doublon) pour région {$region->code}", [
                    'region_id' => $region->id,
                    'region_code' => $region->code,
                    'regional_id' => $request->input('regional_id'),
                    'error' => $e->getMessage(),
                ]);

                // Mettre à jour l'erreur dans la région
                $region->update([
                    'derniere_erreur' => 'Erreur de contrainte unique (doublon détecté)',
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Doublon détecté. Le dashboard avec ce regional_id existe déjà.',
                    'error' => config('app.debug') ? $e->getMessage() : 'Erreur de contrainte unique',
                ], 409); // 409 Conflict
            }

            // Autres erreurs de base de données
            Log::error("Erreur de base de données lors de la réception du dashboard pour région {$region->code}", [
                'region_id' => $region->id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            $region->update([
                'derniere_erreur' => 'Erreur de base de données: ' . substr($e->getMessage(), 0, 255),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Erreur de base de données',
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur interne du serveur',
            ], 500);

        } catch (\Exception $e) {
            Log::error("Erreur lors de la réception du dashboard pour région {$region->code}", [
                'region_id' => $region->id,
                'region_code' => $region->code,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mettre à jour l'erreur dans la région
            $region->update([
                'derniere_erreur' => substr($e->getMessage(), 0, 255),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors du stockage des données',
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur interne du serveur',
            ], 500);
        }
    }
}
