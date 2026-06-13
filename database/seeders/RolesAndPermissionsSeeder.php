<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['nom' => 'gerer_utilisateurs', 'description' => 'Gérer les utilisateurs'],
            ['nom' => 'gerer_roles', 'description' => 'Gérer les rôles'],
            ['nom' => 'gerer_permissions', 'description' => 'Gérer les permissions'],
            ['nom' => 'voir_mandats', 'description' => 'Voir les mandats'],
            ['nom' => 'gerer_mandats', 'description' => 'Gérer les mandats'],
            ['nom' => 'voir_recettes', 'description' => 'Voir les recettes'],
            ['nom' => 'gerer_recettes', 'description' => 'Gérer les recettes'],
            ['nom' => 'voir_banques', 'description' => 'Voir les banques'],
            ['nom' => 'gerer_banques', 'description' => 'Gérer les banques'],
            ['nom' => 'voir_dashboard', 'description' => 'Voir le tableau de bord'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['nom' => $permission['nom']], $permission);
        }

        $adminRole = Role::firstOrCreate(
            ['nom' => 'Administrateur'],
            ['description' => 'Administrateur avec tous les droits']
        );

        $compta = Role::firstOrCreate(
            ['nom' => 'Comptable'],
            ['description' => 'Agent comptable']
        );

        $visualisateur = Role::firstOrCreate(
            ['nom' => 'Visualisateur'],
            ['description' => 'Utilisateur en lecture seule']
        );

        $adminRole->permissions()->sync(Permission::pluck('id'));
        $compta->permissions()->sync(Permission::whereIn('nom', [
            'voir_mandats', 'gerer_mandats',
            'voir_recettes', 'gerer_recettes',
            'voir_banques', 'gerer_banques',
            'voir_dashboard',
        ])->pluck('id'));
        $visualisateur->permissions()->sync(Permission::whereIn('nom', [
            'voir_mandats', 'voir_recettes', 'voir_banques', 'voir_dashboard',
        ])->pluck('id'));

        if (! User::where('login', 'admin')->exists()) {
            User::create([
                'nom' => 'Administrateur',
                'prenom' => 'Système',
                'email' => env('ADMIN_EMAIL', 'admin@dgtcp.local'),
                'login' => env('ADMIN_LOGIN', 'admin'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'ChangeMe2026!')),
                'role_id' => $adminRole->id,
                'premiere_connexion' => true,
                'actif' => true,
            ]);
        }
    }
}
