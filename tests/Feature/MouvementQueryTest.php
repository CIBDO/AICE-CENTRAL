<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\User;
use Database\Seeders\DemoDashboardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MouvementQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_mouvements_api_returns_stats_and_pagination(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Region::create([
            'code' => 'RGF',
            'nom' => 'Région du Fleuve',
            'actif' => true,
            'token' => 't1',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        (new DemoDashboardSeeder())->run();

        $response = $this->getJson('/api/v1/mouvements?region_code=RGF&annee=' . now()->year . '&mois=' . now()->month . '&type=depense');

        $response->assertOk();
        $response->assertJsonStructure(['stats' => ['totaux', 'par_statut', 'par_jour'], 'data', 'meta']);
        $response->assertJsonPath('meta.total', fn ($v) => $v > 0);
    }
}
