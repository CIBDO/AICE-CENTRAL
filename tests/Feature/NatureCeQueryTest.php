<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\User;
use Database\Seeders\DemoDashboardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NatureCeQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_natures_ce_api_returns_aggregated_stats(): void
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

        $response = $this->getJson('/api/v1/natures-ce?region_code=RGF&annee=' . now()->year . '&mois=' . now()->month);

        $response->assertOk();
        $response->assertJsonStructure([
            'stats' => ['totaux', 'natures_ce', 'par_statut', 'par_chapitre', 'par_jour'],
            'data',
            'meta',
        ]);
        $response->assertJsonPath('stats.totaux.natures_ce_count', fn ($v) => $v >= 1);
    }

    public function test_natures_ce_export_returns_csv(): void
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

        $response = $this->get('/api/v1/natures-ce/export?region_code=RGF&annee=' . now()->year . '&mois=' . now()->month);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
