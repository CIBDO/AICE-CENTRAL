<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Region;

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
}
