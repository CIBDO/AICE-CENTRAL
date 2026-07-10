<?php

namespace Tests\Feature;

use App\Models\Dashboard;
use App\Models\Mouvement;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExecutiveDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_executive_kpis_returns_indicators(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'RGF',
            'nom' => 'Région du Fleuve',
            'actif' => true,
            'token' => 't1',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        $dashboard = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'RGF',
            'regional_id' => 'D1',
            'total_ordonnance' => 400,
            'total_recouvrements_4121' => 1000,
            'total_montant_paye' => 100,
            'solde' => 600,
            'tresorerie_reelle' => 50,
            'annee' => 2024,
            'mois' => 6,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'M1',
            'libelle' => 'Mandat test',
            'montant' => 100,
            'type' => 'depense',
            'type_mandat' => '0',
            'annee' => 2024,
            'mois' => 6,
            'statut' => 'Payé',
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'M2',
            'libelle' => 'Mandat rejeté',
            'montant' => 50,
            'type' => 'depense',
            'type_mandat' => '1',
            'annee' => 2024,
            'mois' => 6,
            'statut' => 'Rejeté',
        ]);

        $response = $this->getJson('/api/v1/executive/kpis?annee=2024&mois=6');

        $response->assertOk();
        $response->assertJsonPath('data.indicateurs.mandats_total', 2);
        $response->assertJsonPath('data.indicateurs.recouvrements_4121_total', 1000);
        $response->assertJsonPath('data.meta.mandats_count', 2);
        $response->assertJsonPath('data.meta.recettes_count', 0);
        $response->assertJsonPath('data.meta.mouvements_count', 2);
    }

    public function test_executive_kpis_count_nav_lines_for_mandats_total(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'SAN',
            'nom' => 'San',
            'actif' => true,
            'token' => 't-san',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        $dashboard = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'SAN',
            'regional_id' => 'D-SAN',
            'total_ordonnance' => 0,
            'total_recouvrements_4121' => 0,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 0,
            'annee' => 2024,
            'mois' => 12,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'push-1',
            'libelle' => 'Mandat SAN ligne 1',
            'montant' => 100,
            'type' => 'depense',
            'type_mandat' => '0',
            'source_numero_mandat' => 'MDT-001',
            'annee' => 2024,
            'mois' => 12,
            'date_mouvement' => '2024-12-10',
            'statut' => 'Transmis',
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'push-2',
            'libelle' => 'Mandat SAN ligne 2',
            'montant' => 200,
            'type' => 'depense',
            'type_mandat' => '0',
            'source_numero_mandat' => 'MDT-001',
            'annee' => 2024,
            'mois' => 12,
            'date_mouvement' => '2024-12-11',
            'statut' => 'Visé',
        ]);

        $response = $this->getJson('/api/v1/executive/kpis?' . http_build_query([
            'region_code' => 'SAN',
            'date_debut' => '2024-01-01',
            'date_fin' => '2024-12-31',
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.indicateurs.mandats_total', 2);
        $response->assertJsonPath('data.meta.mandats_count', 2);
    }

    public function test_executive_alertes_lists_region_without_data(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Region::create([
            'code' => 'RGD',
            'nom' => 'Région de Dakar',
            'actif' => true,
            'token' => 't2',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        $response = $this->getJson('/api/v1/executive/alertes?annee=2024&mois=6');

        $response->assertOk();
        $response->assertJsonFragment(['categorie' => 'donnees']);
    }
}
