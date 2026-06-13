<?php

namespace App\Services;

use App\Models\User;

class AuthAbilityService
{
    private const MANAGE_PREFIX = 'gerer_';

    /**
     * @return array<int, array{action: string, subject: string}>
     */
    public function rulesFor(User $user): array
    {
        if ($user->hasRole('Administrateur')) {
            return [
                ['action' => 'manage', 'subject' => 'all'],
            ];
        }

        $user->loadMissing('role.permissions');

        $rules = [];
        foreach ($user->role?->permissions ?? [] as $permission) {
            $action = str_starts_with($permission->nom, self::MANAGE_PREFIX) ? 'manage' : 'read';
            $rules[] = [
                'action' => $action,
                'subject' => $permission->nom,
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function userPayload(User $user): array
    {
        $user->loadMissing('role');

        return [
            'id' => $user->id,
            'login' => $user->login,
            'email' => $user->email,
            'fullName' => $user->getNomComplet(),
            'username' => $user->login,
            'role' => $user->role?->nom,
            'premiereConnexion' => (bool) $user->premiere_connexion,
        ];
    }
}
