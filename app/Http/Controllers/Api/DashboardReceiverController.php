<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDashboardChunk;
use App\Services\DashboardReceiveService;
use App\Support\DashboardKpis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Dashboard;
use App\Models\Mouvement;

class DashboardReceiverController extends Controller
{
    public function __construct(private readonly DashboardReceiveService $service)
    {
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

        $request->merge(DashboardKpis::normalizeIncomingPayload($request->all()));

        // Validation des données
        $validator = Validator::make($request->all(), [
            'local_id' => 'required|string|max:100',
            'regional_id' => 'required|string|max:100',
            'total_ordonnance' => 'required|numeric|min:0',
            'total_recouvrements_4121' => 'required|numeric|min:0',
            'total_montant_paye' => 'required|numeric|min:0',
            'solde' => 'required|numeric',
            'tresorerie_reelle' => 'required|numeric',
            // Champs de période (optionnels mais recommandés pour le filtrage)
            'annee' => 'nullable|integer|min:2000|max:2100',
            'mois' => 'nullable|integer|min:1|max:12',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            // IMPORTANT: on veut "obligatoire" mais accepter [] => required échoue sur tableau vide en Laravel.
            // `present` impose la présence de la clé, sans exiger de contenu.
            'mouvements' => 'present|array',
            'mouvements.*.regional_id' => 'required|string|max:100', // ID unique côté régional pour chaque mouvement
            'mouvements.*.libelle' => 'required|string|max:255',
            'mouvements.*.montant' => 'required|numeric',
            'mouvements.*.type' => 'required|in:recette,depense',
            // Champs optionnels pour les mouvements (pour filtrage avancé)
            'mouvements.*.date_mouvement' => 'nullable|date',
            'mouvements.*.programme' => 'nullable|string|max:100',
            'mouvements.*.nature' => 'nullable|string|max:100',
            // Champs analytiques enrichis (optionnels)
            'mouvements.*.code_programme' => 'nullable|string|max:100',
            'mouvements.*.chapitre' => 'nullable|string|max:100',
            'mouvements.*.nature_ce' => 'nullable|string|max:100',
            'mouvements.*.statut' => 'nullable|string|max:50',
            'mouvements.*.statut_code' => 'nullable|string|max:10',
            'mouvements.*.montant_paye' => 'nullable|numeric',
            'mouvements.*.solde_a_payer' => 'nullable|numeric',
            'mouvements.*.beneficiaire' => 'nullable|string|max:255',
            'mouvements.*.source_numero_mandat' => 'nullable|string|max:100',
            'mouvements.*.source_id' => 'nullable|string|max:100',
            'mouvements.*.type_mandat' => 'nullable|string|max:10|in:0,1,2',
            'mouvements.*.type_mandat_libelle' => 'nullable|string|max:50|in:Matériel,Salaire,Reversement',

            // Section banques[] (optionnelle - mode Push enrichi)
            'banques' => 'nullable|array',
            'banques.*.numero_compte' => 'required_with:banques|string|max:50',
            'banques.*.libelle' => 'required_with:banques|string|max:255',
            'banques.*.date_mouvement' => 'nullable|date',
            'banques.*.debit' => 'nullable|numeric',
            'banques.*.credit' => 'nullable|numeric',
            'banques.*.solde' => 'nullable|numeric',
            'banques.*.reference' => 'nullable|string|max:100',
            'banques.*.entry_no' => 'nullable|string|max:50',
            'banques.*.exercice' => 'nullable|integer|min:2000|max:2100',
            'banques.*.type_document' => 'nullable|string|max:50',
            'banques.*.description' => 'nullable|string',
            'banques.*.regional_id' => 'nullable|string|max:100',

            // Section recettes_clients[] (optionnelle - mode Push enrichi)
            'recettes_clients' => 'nullable|array',
            'recettes_clients.*.client_no' => 'required_with:recettes_clients|string|max:50',
            'recettes_clients.*.client_name' => 'required_with:recettes_clients|string|max:255',
            'recettes_clients.*.gl_account' => 'nullable|string|max:50',
            'recettes_clients.*.date_posting' => 'nullable|date',
            'recettes_clients.*.montant' => 'nullable|numeric|min:0',
            'recettes_clients.*.description' => 'nullable|string',
            'recettes_clients.*.source_no' => 'nullable|string|max:50',
            'recettes_clients.*.exercice' => 'nullable|integer|min:2000|max:2100',
            'recettes_clients.*.regional_id' => 'nullable|string|max:100',

            // Infos de chunk (optionnel)
            'chunk_info' => 'nullable|array',
            'chunk_info.current' => 'nullable|integer|min:1',
            'chunk_info.total' => 'nullable|integer|min:1',
            'chunk_info.chunk_size' => 'nullable|integer|min:1',
        ], [
            'regional_id.required' => 'Le champ regional_id est requis pour le mode différentiel',
            'mouvements.*.regional_id.required' => 'Chaque mouvement doit avoir un regional_id unique',
            'local_id.required' => 'Le champ local_id est requis',
            'total_ordonnance.required' => 'Le champ total_ordonnance est requis',
            'total_ordonnance.numeric' => 'Le champ total_ordonnance doit être un nombre',
            'total_recouvrements_4121.required' => 'Le champ total_recouvrements_4121 est requis',
            'total_recouvrements_4121.numeric' => 'Le champ total_recouvrements_4121 doit être un nombre',
            'total_montant_paye.required' => 'Le champ total_montant_paye est requis',
            'total_montant_paye.numeric' => 'Le champ total_montant_paye doit être un nombre',
            'solde.required' => 'Le champ solde est requis',
            'solde.numeric' => 'Le champ solde doit être un nombre',
            'tresorerie_reelle.required' => 'Le champ tresorerie_reelle est requis',
            'tresorerie_reelle.numeric' => 'Le champ tresorerie_reelle doit être un nombre',
            'mouvements.present' => 'Le champ mouvements est requis (il peut être un tableau vide).',
            'mouvements.array' => 'Le champ mouvements doit être un tableau',
            'mouvements.*.libelle.required' => 'Chaque mouvement doit avoir un libellé',
            'mouvements.*.montant.required' => 'Chaque mouvement doit avoir un montant',
            'mouvements.*.montant.numeric' => 'Le montant doit être un nombre',
            'mouvements.*.type.required' => 'Chaque mouvement doit avoir un type',
            'mouvements.*.type.in' => 'Le type doit être "recette" ou "depense"',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $errorsArray = $errors->toArray();
            
            // Identifier les mouvements problématiques avec leur index
            $problematicMouvements = [];
            foreach ($errorsArray as $key => $messages) {
                if (preg_match('/^mouvements\.(\d+)\.(.+)$/', $key, $matches)) {
                    $mouvementIndex = $matches[1];
                    $field = $matches[2];
                    
                    if (!isset($problematicMouvements[$mouvementIndex])) {
                        $problematicMouvements[$mouvementIndex] = [
                            'index' => (int)$mouvementIndex,
                            'errors' => []
                        ];
                    }
                    
                    $problematicMouvements[$mouvementIndex]['errors'][$field] = $messages;
                    
                    // Ajouter un aperçu du mouvement problématique (premiers 100 caractères)
                    if (!isset($problematicMouvements[$mouvementIndex]['data'])) {
                        $mouvementData = $request->input("mouvements.{$mouvementIndex}");
                        if ($mouvementData) {
                            $problematicMouvements[$mouvementIndex]['data'] = [
                                'regional_id' => $mouvementData['regional_id'] ?? 'N/A',
                                'libelle' => mb_substr($mouvementData['libelle'] ?? 'N/A', 0, 100),
                                'montant' => $mouvementData['montant'] ?? 'N/A',
                                'type' => $mouvementData['type'] ?? 'N/A',
                            ];
                        }
                    }
                }
            }
            
            Log::warning("Validation échouée pour région {$region->code}", [
                'total_mouvements' => count($request->input('mouvements', [])),
                'mouvements_problematiques' => count($problematicMouvements),
                'errors_summary' => [
                    'total_errors' => count($errorsArray),
                    'fields_with_errors' => array_keys($errorsArray),
                ],
                'problematic_mouvements' => array_slice($problematicMouvements, 0, 10), // Limiter à 10 pour les logs
                'payload_summary' => $request->except(['mouvements']),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Données invalides',
                'errors' => $errorsArray,
                'summary' => [
                    'total_mouvements' => count($request->input('mouvements', [])),
                    'mouvements_problematiques' => count($problematicMouvements),
                    'total_errors' => count($errorsArray),
                ],
                'problematic_mouvements' => array_values($problematicMouvements), // Retourner tous les mouvements problématiques
            ], 422);
        }

        // Sécurité: éviter qu'une région envoie un local_id qui ne correspond pas à son token.
        // (Permet d’identifier la région via le token, et de prévenir la fraude/désalignement.)
        $normalizeRegionCode = static function (?string $value): string {
            // Normalisation simple: trim + uppercase (évite les 403 pour différences de casse/espaces)
            return mb_strtoupper(trim((string) $value));
        };

        if ($normalizeRegionCode($request->input('local_id')) !== $normalizeRegionCode($region->code)) {
            Log::warning('Mismatch local_id vs région authentifiée', [
                'region_code' => $region->code,
                'local_id' => $request->input('local_id'),
                'regional_id' => $request->input('regional_id'),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'local_id ne correspond pas à la région authentifiée par X-REGION-TOKEN. Attendu: ' . $region->code,
            ], 403);
        }

        try {
            $validated = $validator->validated();

            // Option: traiter en queue pour éviter les timeouts lors des gros chunks
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
