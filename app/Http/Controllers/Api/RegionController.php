<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    /**
     * GET /api/v1/regions
     */
    public function index()
    {
        $regions = Region::query()
            ->actives()
            ->ordered()
            ->get(['id', 'code', 'nom', 'ordre', 'derniere_connexion']);

        return response()->json([
            'status' => 'OK',
            'data' => $regions->map(fn (Region $region) => [
                'id' => $region->id,
                'code' => $region->code,
                'nom' => $region->nom,
                'ordre' => $region->ordre,
                'derniere_connexion' => $region->derniere_connexion?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * GET /api/v1/regions/admin
     */
    public function adminIndex(): JsonResponse
    {
        $regions = Region::query()
            ->ordered()
            ->get();

        return response()->json([
            'status' => 'OK',
            'data' => $regions->map(fn (Region $region) => $this->adminPayload($region)),
        ]);
    }

    /**
     * PUT /api/v1/regions/{region}
     */
    public function update(Request $request, Region $region): JsonResponse
    {
        $validated = $request->validate([
            'nom' => ['sometimes', 'required', 'string', 'max:100'],
            'actif' => ['sometimes', 'boolean'],
            'ordre' => ['sometimes', 'integer', 'min:0'],
        ]);

        $region->update($validated);

        return response()->json([
            'status' => 'OK',
            'data' => $this->adminPayload($region->fresh()),
        ]);
    }

    /** @return array<string, mixed> */
    private function adminPayload(Region $region): array
    {
        return [
            'id' => $region->id,
            'code' => $region->code,
            'nom' => $region->nom,
            'db_host' => $region->db_host,
            'db_port' => $region->db_port,
            'db_database' => $region->db_database,
            'db_username' => $region->db_username,
            'db_charset' => $region->db_charset,
            'source_type' => $region->source_type,
            'api_url' => $region->api_url,
            'actif' => (bool) $region->actif,
            'ordre' => $region->ordre,
            'metadata' => $region->metadata,
            'derniere_connexion' => $region->derniere_connexion?->toIso8601String(),
            'derniere_erreur' => $region->derniere_erreur,
            'token' => $this->maskToken($region->getRawOriginal('token')),
            'created_at' => $region->created_at?->toIso8601String(),
            'updated_at' => $region->updated_at?->toIso8601String(),
        ];
    }

    private function maskToken(?string $token): ?string
    {
        if (empty($token)) {
            return null;
        }

        $length = strlen($token);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . substr($token, -4);
    }
}
