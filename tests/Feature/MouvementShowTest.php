<?php

namespace Tests\Feature;

use App\Models\Dashboard;
use App\Models\Mouvement;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\DemoDashboardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MouvementShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_mouvement_show_returns_detail_and_related(): void
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

        $mouvement = Mouvement::query()->where('type', 'depense')->first();
        $this->assertNotNull($mouvement);

        $response = $this->getJson('/api/v1/mouvements/' . $mouvement->id . '?region_code=RGF&annee=' . $mouvement->annee . '&mois=' . $mouvement->mois);

        $response->assertOk();
        $response->assertJsonStructure(['data', 'related', 'context' => ['region_code', 'region_nom']]);
        $response->assertJsonPath('data.id', $mouvement->id);
    }

    public function test_mouvements_export_returns_csv(): void
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

        $response = $this->get('/api/v1/mouvements/export?region_code=RGF&annee=' . now()->year . '&mois=' . now()->month . '&type=depense');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_mouvements_export_falls_back_to_nature_when_nature_ce_is_empty(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'SAN',
            'nom' => 'San',
            'actif' => true,
            'token' => 't1',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        $dashboard = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'SAN',
            'regional_id' => 'D1',
            'total_ordonnance' => 1000,
            'total_recouvrements_4121' => 0,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 0,
            'annee' => 2024,
            'mois' => 12,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'M-1',
            'libelle' => 'Mandat nature fallback',
            'montant' => 1500,
            'type' => 'depense',
            'date_mouvement' => '2024-12-15',
            'annee' => 2024,
            'mois' => 12,
            'code_programme' => '2041',
            'beneficiaire' => 'Fournisseur A',
            'source_numero_mandat' => 'MDT-001',
            'nature' => '64-9-1-15',
            'nature_ce' => null,
            'statut' => 'Transmis',
            'type_mandat' => '0',
            'type_mandat_libelle' => 'Matériel',
        ]);

        $response = $this->get('/api/v1/mouvements/export?' . http_build_query([
            'region_code' => 'SAN',
            'annee' => 2024,
            'mois' => 12,
            'type' => 'depense',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('64-9-1-15', $response->streamedContent());
    }
}
