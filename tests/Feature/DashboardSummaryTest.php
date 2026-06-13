<?php

namespace Tests\Feature;

use App\Models\Dashboard;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_returns_kpis_for_region(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'RGF',
            'nom' => 'Région du Fleuve',
            'actif' => true,
            'token' => 'token-test',
            'source_type' => 'api',
        ]);

        Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'RGF',
            'regional_id' => 'DASH-RGF-2024-06',
            'total_recettes' => 1000,
            'total_depenses' => 400,
            'solde' => 600,
            'encaisse' => 50,
            'annee' => 2024,
            'mois' => 6,
        ]);

        $response = $this->getJson('/api/v1/dashboards/summary?region_code=RGF&annee=2024&mois=6');

        $response->assertOk();
        $response->assertJsonPath('data.kpis.total_recettes', 1000);
        $response->assertJsonPath('data.region.code', 'RGF');
    }
}
