<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleAdminTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(string $permissionNom): User
    {
        $permission = Permission::create([
            'nom' => $permissionNom,
            'description' => 'Test permission',
        ]);

        $role = Role::create([
            'nom' => 'Gestionnaire rôles',
            'description' => 'Peut gérer les rôles',
        ]);
        $role->permissions()->attach($permission->id);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_roles_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/roles')->assertUnauthorized();
    }

    public function test_roles_crud_flow(): void
    {
        Sanctum::actingAs($this->userWithPermission('gerer_roles'));

        $voirMandats = Permission::create(['nom' => 'voir_mandats', 'description' => 'Voir les mandats']);
        $gererMandats = Permission::create(['nom' => 'gerer_mandats', 'description' => 'Gérer les mandats']);

        $createResponse = $this->postJson('/api/v1/roles', [
            'nom' => 'Auditeur',
            'description' => 'Lecture mandats uniquement',
            'permission_ids' => [$voirMandats->id],
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('data.nom', 'Auditeur');
        $createResponse->assertJsonPath('data.permission_ids', [$voirMandats->id]);

        $roleId = $createResponse->json('data.id');

        $this->getJson("/api/v1/roles/{$roleId}")
            ->assertOk()
            ->assertJsonPath('data.permissions_count', 1);

        $this->putJson("/api/v1/roles/{$roleId}", [
            'description' => 'Mandats lecture et écriture',
            'permission_ids' => [$voirMandats->id, $gererMandats->id],
        ])->assertOk()
            ->assertJsonPath('data.permissions_count', 2);

        $this->deleteJson("/api/v1/roles/{$roleId}")
            ->assertOk();

        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }

    public function test_role_delete_blocked_when_users_assigned(): void
    {
        Sanctum::actingAs($this->userWithPermission('gerer_roles'));

        $role = Role::create(['nom' => 'Comptable test', 'description' => 'Test']);
        User::factory()->create(['role_id' => $role->id]);

        $this->deleteJson("/api/v1/roles/{$role->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Ce rôle est assigné à des utilisateurs. Réassignez-les avant de le supprimer.');
    }

    public function test_roles_mutations_require_gerer_roles_permission(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $permission = Permission::create(['nom' => 'voir_dashboard', 'description' => 'Voir dashboard']);

        $this->postJson('/api/v1/roles', [
            'nom' => 'Interdit',
            'permission_ids' => [$permission->id],
        ])->assertForbidden();
    }

    public function test_permissions_index_returns_catalog(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Permission::create(['nom' => 'gerer_banques', 'description' => 'Gérer les banques']);

        $this->getJson('/api/v1/permissions')
            ->assertOk()
            ->assertJsonPath('status', 'OK')
            ->assertJsonCount(1, 'data');
    }
}
