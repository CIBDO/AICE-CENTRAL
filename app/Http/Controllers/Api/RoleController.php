<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('nom')
            ->get(['id', 'nom', 'description']);

        return response()->json([
            'status' => 'OK',
            'data' => $roles->map(fn (Role $role) => $this->listPayload($role)),
        ]);
    }

    public function show(Role $role): JsonResponse
    {
        $role->loadCount(['permissions', 'users']);
        $role->load('permissions:id');

        return response()->json([
            'status' => 'OK',
            'data' => $this->detailPayload($role),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255', 'unique:roles,nom'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permission_ids' => ['required', 'array', 'min:1'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = Role::create([
            'nom' => $validated['nom'],
            'description' => $validated['description'] ?? null,
        ]);

        $role->permissions()->sync($validated['permission_ids']);
        $role->loadCount(['permissions', 'users']);
        $role->load('permissions:id');

        return response()->json([
            'status' => 'OK',
            'data' => $this->detailPayload($role),
        ], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'nom' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('roles', 'nom')->ignore($role->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'permission_ids' => ['sometimes', 'required', 'array', 'min:1'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);

        $attributes = array_intersect_key($validated, array_flip(['nom', 'description']));
        if ($attributes !== []) {
            $role->update($attributes);
        }

        if (array_key_exists('permission_ids', $validated)) {
            $role->permissions()->sync($validated['permission_ids']);
        }

        $role->refresh();
        $role->loadCount(['permissions', 'users']);
        $role->load('permissions:id');

        return response()->json([
            'status' => 'OK',
            'data' => $this->detailPayload($role),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->users()->exists()) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Ce rôle est assigné à des utilisateurs. Réassignez-les avant de le supprimer.',
            ], 409);
        }

        $role->permissions()->detach();
        $role->delete();

        return response()->json(['status' => 'OK']);
    }

    /** @return array<string, mixed> */
    private function listPayload(Role $role): array
    {
        return [
            'id' => $role->id,
            'nom' => $role->nom,
            'description' => $role->description,
            'permissions_count' => $role->permissions_count,
            'users_count' => $role->users_count,
        ];
    }

    /** @return array<string, mixed> */
    private function detailPayload(Role $role): array
    {
        return [
            ...$this->listPayload($role),
            'permission_ids' => $role->permissions->pluck('id')->values()->all(),
        ];
    }
}
