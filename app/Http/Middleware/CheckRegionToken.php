<?php

namespace App\Http\Middleware;

use App\Services\PushEventLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Region;
use Throwable;

class CheckRegionToken
{
    public function __construct(private readonly PushEventLogger $pushEventLogger)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('push_event_started_at', microtime(true));
        $request->attributes->set('push_event_received_at', now());
        $request->attributes->set('push_event_correlation_id', (string) \Illuminate\Support\Str::uuid());

        // Récupérer le token depuis l'en-tête
        $token = $request->header('X-REGION-TOKEN');

        if (!$token) {
            Log::warning('Tentative d\'accès API sans token', [
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
            ]);

            $response = response()->json([
                'status' => 'error',
                'message' => 'Token manquant. Fournissez le header X-REGION-TOKEN.',
            ], 401);

            $this->pushEventLogger->logRequest($request, null, $request->attributes->get('push_event_received_at'), $response);

            return $response;
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

            $response = response()->json([
                'status' => 'error',
                'message' => 'Token invalide ou région inactive.',
            ], 401);

            $this->pushEventLogger->logRequest($request, null, $request->attributes->get('push_event_received_at'), $response);

            return $response;
        }

        // Ajouter la région à la requête pour utilisation dans les contrôleurs
        // On utilise les attributes (non sérialisés dans le payload) pour éviter de polluer request->all()
        $request->attributes->set('authenticated_region', $region);

        // Logger l'accès réussi
        Log::info("Accès API autorisé pour région {$region->code}", [
            'region_id' => $region->id,
            'ip' => $request->ip(),
        ]);

        try {
            $response = $next($request);
            $response->headers->set('X-Correlation-ID', (string) $request->attributes->get('push_event_correlation_id'));

            $this->pushEventLogger->logRequest($request, $region, $request->attributes->get('push_event_received_at'), $response);

            return $response;
        } catch (Throwable $e) {
            $this->pushEventLogger->logRequest(
                $request,
                $region,
                $request->attributes->get('push_event_received_at'),
                null,
                $e,
            );

            throw $e;
        }
    }
}
