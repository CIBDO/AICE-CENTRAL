<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RegionsSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            ['code' => 'RGF', 'nom' => 'Région du Fleuve', 'ordre' => 1],
            ['code' => 'RGD', 'nom' => 'Région de Dakar', 'ordre' => 2],
            ['code' => 'RGT', 'nom' => 'Région de Thiès', 'ordre' => 3],
        ];

        foreach ($regions as $index => $data) {
            Region::firstOrCreate(
                ['code' => $data['code']],
                [
                    'nom' => $data['nom'],
                    'ordre' => $data['ordre'],
                    'actif' => true,
                    'source_type' => 'api',
                    'token' => hash('sha256', $data['code'] . '-' . Str::uuid()),
                    'db_host' => 'localhost',
                    'db_port' => 1433,
                    'db_database' => 'legacy',
                    'db_username' => 'legacy',
                    'db_password' => 'legacy',
                    'db_charset' => 'utf8',
                ]
            );
        }
    }
}
