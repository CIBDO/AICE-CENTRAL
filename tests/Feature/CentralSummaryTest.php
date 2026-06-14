<?php

namespace Tests\Feature;

use App\Models\Dashboard;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CentralSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_summary_aggregates_all_regions(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $rgf = Region::create([
            'code' => 'RGF',
            'nom' => 'Région du Fleuve',
            'actif' => true,
            'token' => 't1',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        $rgd = Region::create([
            'code' => 'RGD',
            'nom' => 'Région de Dakar',
            'actif' => true,
            'token' => 't2',
            'source_type' => 'api',
            'ordre' => 2,
        ]);

        Dashboard::create([
            'region_id' => $rgf->id,
            'local_id' => 'RGF',
            'regional_id' => 'D1',
            'total_ordonnance' => 400,
            'total_recouvrements_4121' => 1000,
            'total_montant_paye' => 300,
            'solde' => 600,
            'tresorerie_reelle' => 50,
            'annee' => 2024,
            'mois' => 6,
        ]);

        Dashboard::create([
            'region_id' => $rgd->id,
            'local_id' => 'RGD',
            'regional_id' => 'D2',
            'total_ordonnance' => 800,
            'total_recouvrements_4121' => 2000,
            'total_montant_paye' => 0,
            'solde' => 1200,
            'tresorerie_reelle' => 100,
            'annee' => 2024,
            'mois' => 6,
        ]);

        $response = $this->getJson('/api/v1/central/summary?annee=2024&mois=6');

        $response->assertOk();
        $response->assertJsonPath('data.global.total_recouvrements_4121', 3000);
        $response->assertJsonPath('data.global.total_ordonnance', 1200);
        $response->assertJsonPath('data.meta.regions_avec_donnees', 2);
        $response->assertJsonCount(2, 'data.regions');
    }
}
