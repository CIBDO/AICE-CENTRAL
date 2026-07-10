<?php

namespace App\Services;

use App\Models\PushEvent;
use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PushEventLogger
{
    public function logRequest(
        Request $request,
        ?Region $region,
        Carbon $receivedAt,
        ?Response $response = null,
        ?Throwable $exception = null,
        ?string $fallbackMessage = null,
    ): void {
        try {
            $payload = $this->buildPayload($request, $region, $receivedAt, $response, $exception, $fallbackMessage);
            PushEvent::query()->create($payload);
        } catch (Throwable $loggingException) {
            Log::warning('Impossible d\'écrire un événement push dans push_events.', [
                'endpoint' => '/' . ltrim($request->path(), '/'),
                'error' => $loggingException->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(
        Request $request,
        ?Region $region,
        Carbon $receivedAt,
        ?Response $response,
        ?Throwable $exception,
        ?string $fallbackMessage,
    ): array {
        $statusCode = $response?->getStatusCode() ?? ($exception ? 500 : null);
        $status = ($exception !== null || ($statusCode !== null && $statusCode >= 400)) ? 'ERROR' : 'OK';
        $counts = $this->extractCounts($request);
        $content = $this->extractResponseContent($response);

        return [
            'received_at' => $receivedAt,
            'region_code' => $region?->code,
            'endpoint' => '/' . ltrim($request->path(), '/'),
            'method' => $request->method(),
            'status' => $status,
            'http_status' => $statusCode,
            'duration_ms' => max(0, (int) round((microtime(true) - (float) $request->attributes->get('push_event_started_at', microtime(true))) * 1000)),
            'correlation_id' => $request->attributes->get('push_event_correlation_id'),
            'mandats_count' => $counts['mandats_count'],
            'recettes_count' => $counts['recettes_count'],
            'banques_count' => $counts['banques_count'],
            'message' => $this->resolveMessage($content, $exception, $fallbackMessage),
            'payload_hash' => $this->resolvePayloadHash($request),
            'remote_ip' => $request->ip(),
            'user_agent' => $this->truncate($request->userAgent(), 512),
            'created_at' => now(),
        ];
    }

    /**
     * @return array{mandats_count: int|null, recettes_count: int|null, banques_count: int|null}
     */
    private function extractCounts(Request $request): array
    {
        $override = $request->attributes->get('push_event_counts');
        if (is_array($override)) {
            return [
                'mandats_count' => isset($override['mandats_count']) ? (int) $override['mandats_count'] : null,
                'recettes_count' => isset($override['recettes_count']) ? (int) $override['recettes_count'] : null,
                'banques_count' => isset($override['banques_count']) ? (int) $override['banques_count'] : null,
            ];
        }

        return [
            'mandats_count' => is_array($request->input('mouvements')) ? count($request->input('mouvements')) : null,
            'recettes_count' => is_array($request->input('recettes_clients')) ? count($request->input('recettes_clients')) : null,
            'banques_count' => is_array($request->input('banques')) ? count($request->input('banques')) : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractResponseContent(?Response $response): ?array
    {
        if ($response === null || !method_exists($response, 'getContent')) {
            return null;
        }

        $content = $response->getContent();
        if (!is_string($content) || trim($content) === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function resolveMessage(?array $content, ?Throwable $exception, ?string $fallbackMessage): ?string
    {
        if ($fallbackMessage !== null && trim($fallbackMessage) !== '') {
            return $this->truncate($fallbackMessage, 1000);
        }

        if ($exception !== null) {
            return $this->truncate($exception->getMessage(), 1000);
        }

        $message = $content['message'] ?? $content['error'] ?? null;
        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE);
        }

        return $this->truncate(is_string($message) ? $message : null, 1000);
    }

    private function resolvePayloadHash(Request $request): ?string
    {
        $content = $request->getContent();
        if (!is_string($content) || trim($content) === '') {
            return null;
        }

        return hash('sha256', $content, true);
    }

    private function truncate(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr(trim($value), 0, $maxLength);
    }
}
