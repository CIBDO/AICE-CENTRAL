<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\PushEvent;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushObservabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('push_events', function (Blueprint $table) {
            $table->id();
            $table->dateTime('received_at')->nullable();
            $table->string('region_code', 50)->nullable();
            $table->string('endpoint', 255);
            $table->string('method', 10)->nullable();
            $table->string('status', 20);
            $table->integer('http_status')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->char('correlation_id', 36)->nullable();
            $table->integer('mandats_count')->nullable();
            $table->integer('recettes_count')->nullable();
            $table->integer('banques_count')->nullable();
            $table->string('message', 1000)->nullable();
            $table->binary('payload_hash')->nullable();
            $table->string('remote_ip', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->dateTime('created_at')->nullable();
        });
    }

    private function userWithPermission(string $permissionNom): User
    {
        $permission = Permission::create([
            'nom' => $permissionNom,
            'description' => 'Test permission',
        ]);

        $role = Role::create([
            'nom' => 'Admin IT',
            'description' => 'Peut consulter l\'observabilité',
        ]);
        $role->permissions()->attach($permission->id);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_push_events_regions_endpoint_requires_permission(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/push-events/regions')
            ->assertForbidden();
    }

    public function test_push_events_endpoints_return_summary_and_timeline(): void
    {
        Sanctum::actingAs($this->userWithPermission('gerer_observabilite_push'));

        Region::create([
            'code' => 'SAN',
            'nom' => 'San',
            'actif' => true,
            'token' => 'san-token',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        PushEvent::query()->create([
            'received_at' => now()->subMinutes(10),
            'region_code' => 'SAN',
            'endpoint' => '/api/v1/receive/dashboard',
            'method' => 'POST',
            'status' => 'OK',
            'http_status' => 200,
            'duration_ms' => 120,
            'mandats_count' => 12,
            'recettes_count' => 2,
            'banques_count' => 1,
            'message' => 'Push reçu avec succès.',
            'created_at' => now()->subMinutes(10),
        ]);

        PushEvent::query()->create([
            'received_at' => now()->subMinutes(5),
            'region_code' => 'SAN',
            'endpoint' => '/api/v1/receive/dashboard',
            'method' => 'POST',
            'status' => 'ERROR',
            'http_status' => 422,
            'duration_ms' => 95,
            'mandats_count' => 3,
            'recettes_count' => 0,
            'banques_count' => 0,
            'message' => 'Données invalides',
            'created_at' => now()->subMinutes(5),
        ]);

        $this->getJson('/api/v1/push-events/regions')
            ->assertOk()
            ->assertJsonPath('data.summary.total_events', 2)
            ->assertJsonPath('data.summary.errors_count', 1)
            ->assertJsonPath('data.regions.0.region.code', 'SAN')
            ->assertJsonPath('data.regions.0.last_status', 'ERROR');

        $this->getJson('/api/v1/push-events')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.region_code', 'SAN');
    }

    public function test_receive_dashboard_logs_push_event(): void
    {
        $region = Region::create([
            'code' => 'RGD-001',
            'nom' => 'Région Test',
            'actif' => true,
            'token' => 'test-token-1234567890',
            'db_host' => 'localhost',
            'db_port' => 1433,
            'db_database' => 'dummy',
            'db_username' => 'dummy',
            'db_password' => 'dummy',
            'db_charset' => 'utf8',
            'source_type' => 'api',
        ]);

        $payload = [
            'local_id' => 'RGD-001',
            'regional_id' => 'DASHBOARD-RGD-001-2024-01-CHUNK-1',
            'total_recettes' => 0,
            'total_depenses' => 0,
            'solde' => 0,
            'encaisse' => 0,
            'annee' => 2024,
            'date_debut' => '2024-01-01',
            'date_fin' => '2024-01-31',
            'mouvements' => [],
            'chunk_info' => [
                'current' => 1,
                'total' => 1,
                'chunk_size' => 500,
            ],
        ];

        $this->withHeader('X-REGION-TOKEN', $region->token)
            ->postJson('/api/v1/receive/dashboard', $payload)
            ->assertOk();

        $this->assertDatabaseHas('push_events', [
            'region_code' => 'RGD-001',
            'endpoint' => '/api/v1/receive/dashboard',
            'status' => 'OK',
            'http_status' => 200,
        ]);
    }
}
