<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('nom')
            ->get(['id', 'nom', 'description']);

        return response()->json([
            'status' => 'OK',
            'data' => $permissions->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'nom' => $permission->nom,
                'description' => $permission->description,
            ]),
        ]);
    }
}
