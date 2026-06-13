<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_and_abilities(): void
    {
        $role = Role::create(['nom' => 'Administrateur', 'description' => 'Admin']);
        $role->permissions()->attach(Permission::create(['nom' => 'voir_dashboard', 'description' => 'Voir']));

        User::create([
            'nom' => 'Admin',
            'prenom' => 'Test',
            'email' => 'admin@test.local',
            'login' => 'admin',
            'password' => Hash::make('secret'),
            'role_id' => $role->id,
            'actif' => true,
            'premiere_connexion' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'admin',
            'password' => 'secret',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['accessToken', 'userData', 'userAbilityRules']);
        $response->assertJsonPath('userData.login', 'admin');
        $response->assertJsonPath('userAbilityRules.0.action', 'manage');
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::create([
            'nom' => 'Inactif',
            'prenom' => 'User',
            'email' => 'inactive@test.local',
            'login' => 'inactive',
            'password' => Hash::make('secret'),
            'actif' => false,
            'premiere_connexion' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'inactive',
            'password' => 'secret',
        ]);

        $response->assertUnprocessable();
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/regions')->assertUnauthorized();
    }
}
