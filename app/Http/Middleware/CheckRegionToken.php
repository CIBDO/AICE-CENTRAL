<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Region;

class CheckRegionToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Récupérer le token depuis l'en-tête
        $token = $request->header('X-REGION-TOKEN');

        if (!$token) {
            Log::warning('Tentative d\'accès API sans token', [
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Token manquant. Fournissez le header X-REGION-TOKEN.',
            ], 401);
        }

        // Vérifier que le token existe dans la table regions
        $region = Region::where('token', $token)
            ->where('actif', true)
            ->first();

        if (!$region) {
            Log::warning('Tentative d\'accès API avec token invalide', [
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
                'token_provided' => substr($token, 0, 10) . '...',
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Token invalide ou région inactive.',
            ], 401);
        }

        // Ajouter la région à la requête pour utilisation dans les contrôleurs
        // On utilise les attributes (non sérialisés dans le payload) pour éviter de polluer request->all()
        $request->attributes->set('authenticated_region', $region);

        // Logger l'accès réussi
        Log::info("Accès API autorisé pour région {$region->code}", [
            'region_id' => $region->id,
            'ip' => $request->ip(),
        ]);

        return $next($request);
    }
}
