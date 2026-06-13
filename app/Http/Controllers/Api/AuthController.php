<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthAbilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthAbilityService $abilities,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('login', $credentials['login'])
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Identifiants incorrects.'],
            ]);
        }

        if (!$user->actif) {
            throw ValidationException::withMessages([
                'login' => ['Votre compte a été désactivé. Veuillez contacter l\'administrateur.'],
            ]);
        }

        $user->tokens()->where('name', 'spa')->delete();
        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'accessToken' => $token,
            'userData' => $this->abilities->userPayload($user),
            'userAbilityRules' => $this->abilities->rulesFor($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['status' => 'OK']);
    }

    public function user(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('role');

        return response()->json([
            'status' => 'OK',
            'userData' => $this->abilities->userPayload($user),
            'userAbilityRules' => $this->abilities->rulesFor($user),
        ]);
    }

    public function firstLogin(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->premiere_connexion) {
            return response()->json(['status' => 'OK', 'message' => 'Compte déjà configuré.']);
        }

        $validated = $request->validate([
            'login' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'login')->ignore($user->id),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update([
            'login' => $validated['login'],
            'password' => $validated['password'],
            'premiere_connexion' => false,
        ]);

        $user->refresh()->loadMissing('role');

        return response()->json([
            'status' => 'OK',
            'userData' => $this->abilities->userPayload($user),
            'userAbilityRules' => $this->abilities->rulesFor($user),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mot de passe actuel incorrect.'],
            ]);
        }

        $user->update(['password' => $validated['password']]);

        return response()->json(['status' => 'OK']);
    }
}
