<?php

namespace Tests\Feature;

use App\Models\BanquePush;
use App\Models\Dashboard;
use App\Models\Mouvement;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $dashboardRgf = Dashboard::create([
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

        $dashboardRgd = Dashboard::create([
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

        Mouvement::create([
            'dashboard_id' => $dashboardRgf->id,
            'regional_id' => 'RGF-M1',
            'libelle' => 'Mandat RGF',
            'montant' => 100,
            'type' => 'depense',
            'type_mandat' => '0',
            'annee' => 2024,
            'mois' => 6,
            'statut' => 'Payé',
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboardRgf->id,
            'regional_id' => 'RGF-R1',
            'libelle' => 'Recette RGF',
            'montant' => 50,
            'type' => 'recette',
            'annee' => 2024,
            'mois' => 6,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboardRgd->id,
            'regional_id' => 'RGD-M1',
            'libelle' => 'Mandat RGD',
            'montant' => 200,
            'type' => 'depense',
            'type_mandat' => '1',
            'annee' => 2024,
            'mois' => 6,
            'statut' => 'Admis',
        ]);

        BanquePush::create([
            'dashboard_id' => $dashboardRgf->id,
            'regional_id' => 'BANQUE-ENTRY-RGF-1',
            'numero_compte' => 'CPT-RGF',
            'libelle' => 'Compte RGF',
            'date_mouvement' => '2024-06-10',
            'debit' => 0,
            'credit' => 0,
            'solde' => 1500,
            'entry_no' => 'RGF-9001',
            'exercice' => 2024,
        ]);

        BanquePush::create([
            'dashboard_id' => $dashboardRgd->id,
            'regional_id' => 'BANQUE-ENTRY-RGD-1',
            'numero_compte' => 'CPT-RGD',
            'libelle' => 'Compte RGD',
            'date_mouvement' => '2024-06-11',
            'debit' => 0,
            'credit' => 0,
            'solde' => 3200,
            'entry_no' => 'RGD-9001',
            'exercice' => 2024,
        ]);

        $response = $this->getJson('/api/v1/central/summary?annee=2024&mois=6');

        $response->assertOk();
        $response->assertJsonPath('data.global.total_recouvrements_4121', 3000);
        $response->assertJsonPath('data.global.total_ordonnance', 1200);
        $response->assertJsonPath('data.global.tresorerie_reelle', 4700);
        $response->assertJsonPath('data.workflow.admis.count', 1);
        $response->assertJsonPath('data.workflow.admis.montant', 200);
        $response->assertJsonPath('data.workflow.autres_non_payes.count', 0);
        $response->assertJsonPath('data.workflow.total_hors_rejet.count', 1);
        $response->assertJsonPath('data.meta.regions_avec_donnees', 2);
        $response->assertJsonPath('data.meta.mandats_count', 2);
        $response->assertJsonPath('data.meta.recettes_count', 1);
        $response->assertJsonPath('data.meta.mouvements_count', 3);
        $response->assertJsonCount(2, 'data.regions');
    }
}
