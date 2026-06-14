<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\UserAccountCreated;
use App\Notifications\UserPasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

        $notificationMessage = $this->sendAccountCreatedNotification($user, $plainPassword);

        $user->load('role:id,nom');

        return response()->json([
            'status' => 'OK',
            'message' => $notificationMessage,
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

        if (
            array_key_exists('actif', $validated)
            && $validated['actif'] === false
            && $request->user()?->id === $user->id
        ) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Vous ne pouvez pas désactiver votre propre compte.',
            ], 403);
        }

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

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        if ($user->email === null || $user->email === '') {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Cet utilisateur n\'a pas d\'adresse e-mail pour la notification.',
            ], 422);
        }

        $plainPassword = Str::password(12, letters: true, numbers: true, symbols: false);

        $user->update([
            'password' => Hash::make($plainPassword),
            'premiere_connexion' => true,
        ]);

        $notificationMessage = $this->sendPasswordResetNotification($user, $plainPassword);
        $user->load('role:id,nom');

        return response()->json([
            'status' => 'OK',
            'message' => $notificationMessage,
            'data' => $this->payload($user),
        ]);
    }

    private function sendAccountCreatedNotification(User $user, string $plainPassword): string
    {
        try {
            $user->notify(new UserAccountCreated($plainPassword));

            Log::info('Notification de création de compte envoyée', [
                'user_id' => $user->id,
                'login' => $user->login,
                'email' => $user->email,
            ]);

            return "Utilisateur {$user->login} créé — un e-mail de notification a été envoyé à {$user->email}.";
        } catch (\Throwable $e) {
            Log::error('Échec envoi notification création de compte', [
                'user_id' => $user->id,
                'login' => $user->login,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return "Utilisateur {$user->login} créé — l'e-mail de notification n'a pas pu être envoyé.";
        }
    }

    private function sendPasswordResetNotification(User $user, string $plainPassword): string
    {
        try {
            $user->notify(new UserPasswordReset($plainPassword));

            Log::info('Notification de réinitialisation de mot de passe envoyée', [
                'user_id' => $user->id,
                'login' => $user->login,
                'email' => $user->email,
            ]);

            return "Mot de passe réinitialisé — un e-mail a été envoyé à {$user->email}.";
        } catch (\Throwable $e) {
            Log::error('Échec envoi notification réinitialisation mot de passe', [
                'user_id' => $user->id,
                'login' => $user->login,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return "Mot de passe réinitialisé — l'e-mail de notification n'a pas pu être envoyé.";
        }
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
