<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthFirstLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_login_updates_credentials(): void
    {
        $user = User::create([
            'nom' => 'Admin',
            'prenom' => 'Test',
            'email' => 'admin@test.local',
            'login' => 'admin',
            'password' => Hash::make('temp1234'),
            'role_id' => Role::create(['nom' => 'Administrateur', 'description' => 'Admin'])->id,
            'actif' => true,
            'premiere_connexion' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/auth/first-login', [
            'login' => 'admin.dgtcp',
            'password' => 'NewSecure1!',
            'password_confirmation' => 'NewSecure1!',
        ]);

        $response->assertOk();
        $response->assertJsonPath('userData.login', 'admin.dgtcp');
        $response->assertJsonPath('userData.premiereConnexion', false);

        $user->refresh();
        $this->assertFalse($user->premiere_connexion);
        $this->assertTrue(Hash::check('NewSecure1!', $user->password));
    }

    public function test_first_login_skipped_when_already_configured(): void
    {
        $user = User::factory()->create(['premiere_connexion' => false, 'login' => 'admin']);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/auth/first-login', [
            'login' => 'newlogin',
            'password' => 'NewSecure1!',
            'password_confirmation' => 'NewSecure1!',
        ]);

        $response->assertOk();
        $this->assertSame('admin', $user->fresh()->login);
    }
}
