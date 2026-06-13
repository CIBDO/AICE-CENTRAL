<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->withCount('permissions')
            ->orderBy('nom')
            ->get(['id', 'nom', 'description']);

        return response()->json([
            'status' => 'OK',
            'data' => $roles->map(fn (Role $role) => [
                'id' => $role->id,
                'nom' => $role->nom,
                'description' => $role->description,
                'permissions_count' => $role->permissions_count,
            ]),
        ]);
    }
}
