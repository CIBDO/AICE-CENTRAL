<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\UserAccountCreated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->with('role:id,nom')
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();

        return response()->json([
            'status' => 'OK',
            'data' => $users->map(fn (User $user) => $this->payload($user)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'login' => ['required', 'string', 'max:255', 'unique:users,login'],
            'password' => ['required', 'string', Password::defaults()],
            'role_id' => ['required', 'exists:roles,id'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        $plainPassword = $validated['password'];

        $user = User::create([
            'nom' => $validated['nom'],
            'prenom' => $validated['prenom'],
            'email' => $validated['email'],
            'login' => $validated['login'],
            'password' => Hash::make($plainPassword),
            'role_id' => $validated['role_id'],
            'actif' => $validated['actif'] ?? true,
            'premiere_connexion' => true,
        ]);

        $user->notify(new UserAccountCreated($plainPassword));

        $user->load('role:id,nom');

        return response()->json([
            'status' => 'OK',
            'data' => $this->payload($user),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'prenom' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'login' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users', 'login')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', Password::defaults()],
            'role_id' => ['sometimes', 'required', 'exists:roles,id'],
            'actif' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('password', $validated) && $validated['password'] !== null) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        $user->load('role:id,nom');

        return response()->json([
            'status' => 'OK',
            'data' => $this->payload($user),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()?->id === $user->id) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 403);
        }

        $user->delete();

        return response()->json(['status' => 'OK']);
    }

    /** @return array<string, mixed> */
    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'nom' => $user->nom,
            'prenom' => $user->prenom,
            'email' => $user->email,
            'login' => $user->login,
            'actif' => (bool) $user->actif,
            'premiere_connexion' => (bool) $user->premiere_connexion,
            'role_id' => $user->role_id,
            'role' => $user->role ? [
                'id' => $user->role->id,
                'nom' => $user->role->nom,
            ] : null,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }
}
