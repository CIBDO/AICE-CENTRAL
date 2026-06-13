<?php

namespace Tests\Feature;

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
}
