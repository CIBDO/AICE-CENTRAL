<?php

namespace Tests\Feature;

use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegionAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_regions_admin_requires_authentication(): void
    {
        $this->getJson('/api/v1/regions/admin')->assertUnauthorized();
    }

    public function test_region_create_generates_token(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/regions', [
            'code' => 'san',
            'nom' => 'Région SAN',
            'ordre' => 5,
            'actif' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.code', 'SAN');
        $response->assertJsonPath('data.nom', 'Région SAN');
        $this->assertNotEmpty($response->json('token_plain'));
        $this->assertMatchesRegularExpression('/\*{2,}.{4}$/', $response->json('data.token_masked'));

        $region = Region::where('code', 'SAN')->first();
        $this->assertSame($response->json('token_plain'), $region->getRawOriginal('token'));
    }

    public function test_region_regenerate_token(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'TST',
            'nom' => 'Test',
            'actif' => true,
            'token' => 'old-token-value',
            'source_type' => 'api',
        ]);

        $response = $this->postJson("/api/v1/regions/{$region->id}/regenerate-token");

        $response->assertOk();
        $this->assertNotSame('old-token-value', $response->json('token_plain'));
        $this->assertSame($response->json('token_plain'), $region->fresh()->getRawOriginal('token'));
    }
}
