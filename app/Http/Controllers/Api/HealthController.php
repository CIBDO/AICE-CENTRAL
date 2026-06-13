<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Health check public (supervision)
     *
     * GET /api/v1/health
     *
     * - Sans authentification
     * - Retourne 200 si l'API répond
     * - Optionnellement vérifie la DB (best-effort)
     */
    public function publicCheck(Request $request)
    {
        $dbStatus = 'unknown';
        try {
            DB::connection()->getPdo();
            $dbStatus = 'connected';
        } catch (\Exception $e) {
            $dbStatus = 'error';
        }

        return response()->json([
            'status' => 'ok',
            'service' => 'dgtcp-central-api',
            'timestamp' => now()->toIso8601String(),
            'checks' => [
                'api' => 'operational',
                'database' => $dbStatus,
            ],
        ], 200);
    }

    /**
     * Health check pour tester la connexion
     *
     * POST /api/v1/health
     *
     * Headers requis:
     * - X-REGION-TOKEN: Token de la région
     */
    public function check(Request $request)
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

        try {
            // Tester la connexion à la base de données
            DB::connection()->getPdo();

            return response()->json([
                'status' => 'ok',
                'message' => 'API opérationnelle',
                'region' => [
                    'code' => $region->code,
                    'name' => $region->nom,
                ],
                'timestamp' => now()->toIso8601String(),
                'checks' => [
                    'database' => 'connected',
                    'api' => 'operational',
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur de connexion à la base de données',
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur interne',
            ], 500);
        }
    }
}
