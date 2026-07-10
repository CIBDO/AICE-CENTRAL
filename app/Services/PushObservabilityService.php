<?php

namespace App\Services;

use App\Models\PushEvent;
use App\Models\Region;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PushObservabilityService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PushEvent>
     */
    public function paginateEvents(array $filters): LengthAwarePaginator
    {
        return $this->eventsQuery($filters)
            ->orderByDesc('received_at')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function regionsOverview(array $filters): array
    {
        $events = $this->eventsQuery($filters)->orderByDesc('received_at')->get();
        $regions = Region::query()->actives()->ordered()->get(['code', 'nom']);
        $retardMinutes = (int) ($filters['retard_minutes'] ?? 180);

        return $regions->map(function (Region $region) use ($events, $retardMinutes) {
            /** @var Collection<int, PushEvent> $regionEvents */
            $regionEvents = $events->where('region_code', $region->code)->values();
            /** @var PushEvent|null $latest */
            $latest = $regionEvents->first();
            $errorCount = $regionEvents->where('status', 'ERROR')->count();
            $successCount = $regionEvents->where('status', 'OK')->count();
            $ageMinutes = $latest?->received_at?->diffInMinutes(now());

            return [
                'region' => [
                    'code' => $region->code,
                    'nom' => $region->nom,
                ],
                'last_received_at' => $latest?->received_at?->toIso8601String(),
                'age_minutes' => $ageMinutes,
                'state' => $this->resolveState($latest, $ageMinutes, $retardMinutes),
                'last_status' => $latest?->status,
                'last_http_status' => $latest?->http_status,
                'last_endpoint' => $latest?->endpoint,
                'last_message' => $latest?->message,
                'mandats_count' => $latest?->mandats_count,
                'recettes_count' => $latest?->recettes_count,
                'banques_count' => $latest?->banques_count,
                'error_count' => $errorCount,
                'success_count' => $successCount,
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function topErrors(array $filters): array
    {
        return $this->eventsQuery($filters)
            ->where('status', 'ERROR')
            ->get()
            ->map(function (PushEvent $event) {
                return [
                    'http_status' => $event->http_status ?? 0,
                    'endpoint' => $event->endpoint,
                    'message_short' => mb_substr((string) ($event->message ?? ''), 0, 200),
                    'last_seen' => $event->received_at?->toIso8601String(),
                ];
            })
            ->groupBy(fn (array $row) => implode('|', [$row['http_status'], $row['endpoint'], $row['message_short']]))
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'http_status' => (int) ($first['http_status'] ?? 0),
                    'endpoint' => (string) ($first['endpoint'] ?? ''),
                    'message_short' => (string) ($first['message_short'] ?? ''),
                    'occurrences' => $group->count(),
                    'last_seen' => $group->max('last_seen'),
                ];
            })
            ->sortByDesc(fn (array $row) => sprintf('%08d|%s', $row['occurrences'], $row['last_seen'] ?? ''))
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $query = $this->eventsQuery($filters);
        $events = (clone $query)->get();

        return [
            'total_events' => $events->count(),
            'errors_count' => $events->where('status', 'ERROR')->count(),
            'success_count' => $events->where('status', 'OK')->count(),
            'regions_count' => $events->pluck('region_code')->filter()->unique()->count(),
            'last_received_at' => $events->max(fn (PushEvent $event) => $event->received_at?->toIso8601String()),
            'mandats_count' => (int) $events->sum(fn (PushEvent $event) => $event->mandats_count ?? 0),
            'recettes_count' => (int) $events->sum(fn (PushEvent $event) => $event->recettes_count ?? 0),
            'banques_count' => (int) $events->sum(fn (PushEvent $event) => $event->banques_count ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PushEvent>
     */
    private function eventsQuery(array $filters): Builder
    {
        $query = PushEvent::query();

        if (!empty($filters['region_code'])) {
            $query->where('region_code', (string) $filters['region_code']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (!empty($filters['endpoint'])) {
            $query->where('endpoint', (string) $filters['endpoint']);
        }

        if (!empty($filters['date_debut'])) {
            $query->where('received_at', '>=', $filters['date_debut'] . ' 00:00:00');
        }

        if (!empty($filters['date_fin'])) {
            $query->where('received_at', '<=', $filters['date_fin'] . ' 23:59:59');
        }

        return $query;
    }

    private function resolveState(?PushEvent $latest, ?int $ageMinutes, int $retardMinutes): string
    {
        if ($latest === null) {
            return 'no_data';
        }

        if ($latest->status === 'ERROR') {
            return 'error';
        }

        if ($ageMinutes !== null && $ageMinutes > $retardMinutes) {
            return 'late';
        }

        return 'ok';
    }
}
