<?php

namespace Tests\Unit;

use App\Support\DashboardKpis;
use PHPUnit\Framework\TestCase;

class DashboardKpisTest extends TestCase
{
    public function test_normalize_recettes_clients_fills_empty_client_name(): void
    {
        $normalized = DashboardKpis::normalizeRecettesClients([
            [
                'client_no' => '18000',
                'client_name' => '',
                'description' => 'Encaissement trésor',
                'source_no' => '1001',
            ],
            [
                'client_no' => '',
                'client_name' => '',
                'description' => '',
                'source_no' => '1002',
            ],
        ]);

        $this->assertSame('Encaissement trésor', $normalized[0]['client_name']);
        $this->assertSame('18000', $normalized[0]['client_no']);
        $this->assertSame('NR-1002', $normalized[1]['client_no']);
        $this->assertSame('Client NR-1002', $normalized[1]['client_name']);
    }
}
