<?php

namespace Tests\Feature;

use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardReceiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_receive_dashboard_accepts_empty_mouvements_array(): void
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

        $response = $this->withHeader('X-REGION-TOKEN', $region->token)
            ->postJson('/api/v1/receive/dashboard', $payload);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'status' => 'OK',
            'regional_id' => $payload['regional_id'],
        ]);
    }
}
