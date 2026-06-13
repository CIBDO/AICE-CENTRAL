<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Notifications\UserAccountCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }

    public function test_users_crud_flow(): void
    {
        Notification::fake();

        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $role = Role::create(['nom' => 'Comptable', 'description' => 'Comptable']);

        $createResponse = $this->postJson('/api/v1/users', [
            'nom' => 'Diallo',
            'prenom' => 'Amadou',
            'email' => 'amadou@test.local',
            'login' => 'adiallo',
            'password' => 'Secret123!',
            'role_id' => $role->id,
            'actif' => true,
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('data.login', 'adiallo');
        $createResponse->assertJsonPath('data.role.nom', 'Comptable');

        $userId = $createResponse->json('data.id');
        $created = User::findOrFail($userId);
        $this->assertTrue(Hash::check('Secret123!', $created->password));

        Notification::assertSentTo($created, UserAccountCreated::class);

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('status', 'OK')
            ->assertJsonCount(2, 'data');

        $this->putJson("/api/v1/users/{$userId}", [
            'nom' => 'Diallo',
            'prenom' => 'Amadou',
            'actif' => false,
        ])->assertOk()
            ->assertJsonPath('data.actif', false);

        $this->deleteJson("/api/v1/users/{$userId}")
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_user_cannot_delete_self(): void
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/users/{$admin->id}")
            ->assertForbidden();
    }

    public function test_login_must_be_unique_on_create(): void
    {
        Sanctum::actingAs(User::factory()->create(['login' => 'existing']));
        $role = Role::create(['nom' => 'Visualisateur', 'description' => 'Lecture seule']);

        $this->postJson('/api/v1/users', [
            'nom' => 'Test',
            'prenom' => 'User',
            'email' => 'other@test.local',
            'login' => 'existing',
            'password' => 'Secret123!',
            'role_id' => $role->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['login']);
    }
}
