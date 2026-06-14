<?php

namespace App\Jobs;

use App\Services\DashboardReceiveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDashboardChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $regionId;
    /** @var array<string,mixed> */
    public array $payload;

    /**
     * @param int $regionId
     * @param array<string,mixed> $payload Payload validé (incluant chunk_info si fourni)
     */
    public function __construct(int $regionId, array $payload)
    {
        $this->regionId = $regionId;
        $this->payload = $payload;
        $this->onQueue('dashboard-receive');
    }

    public function handle(DashboardReceiveService $service): void
    {
        @set_time_limit((int) env('DASHBOARD_RECEIVE_MAX_EXECUTION', 600));

        Log::info('Traitement en queue: dashboard chunk', [
            'region_id' => $this->regionId,
            'local_id' => $this->payload['local_id'] ?? null,
            'regional_id' => $this->payload['regional_id'] ?? null,
            'chunk_info' => $this->payload['chunk_info'] ?? null,
            'mouvements_count' => isset($this->payload['mouvements']) && is_array($this->payload['mouvements'])
                ? count($this->payload['mouvements'])
                : null,
        ]);

        $service->handle($this->regionId, $this->payload);
    }
}







