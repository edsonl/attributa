<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\HashidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Inertia\Inertia;
use Inertia\Response;

class TrackingMaintenanceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Panel/TrackingMaintenance/Index', [
            'title' => 'Tracking e Manutenção',
        ]);
    }

    public function summary(): JsonResponse
    {
        $allKeys = $this->scanKeys($this->trackingPrefix() . ':*');

        $counts = [
            'all' => count($allKeys),
            'campaigns' => count($this->filterKeysByPrefix($allKeys, $this->campaignKeyPrefix())),
            'pageviews' => count($this->filterKeysByPrefix($allKeys, $this->pageviewKeyPrefix())),
            'last_collects' => count($this->filterKeysByPrefix($allKeys, $this->lastCollectKeyPrefix())),
            'hit_gates' => count($this->filterKeysByPrefix($allKeys, $this->hitGateKeyPrefix())),
            'script_templates' => count($this->filterKeysByPrefix($allKeys, $this->scriptTemplateKey())),
        ];

        $memory = $this->summarizeMemoryUsage($allKeys);

        return response()->json([
            'counts' => $counts,
            'memory' => $memory,
            'prefix' => $this->trackingPrefix(),
            'connection' => $this->trackingConnectionName(),
        ]);
    }

    public function script(): JsonResponse
    {
        $key = $this->scriptTemplateKey();
        $payload = $this->readKeyPayload($key);

        return response()->json([
            'item' => [
                'cache_id' => $this->encodeCacheId($key),
                'key' => $key,
                'exists' => $payload !== null,
                'redis_type' => $this->readRedisType($key),
                'ttl_seconds' => $this->readTtl($key),
                'ttl_label' => $this->formatTtlLabel($this->readTtl($key)),
                'memory_bytes' => $this->readMemoryUsage($key),
                'memory_label' => $this->formatBytes($this->readMemoryUsage($key)),
                'payload_size_bytes' => $payload !== null ? strlen($payload) : 0,
                'payload_size_label' => $this->formatBytes($payload !== null ? strlen($payload) : 0),
                'content' => $payload,
                'content_preview' => $payload !== null ? mb_substr($payload, 0, 500) : null,
            ],
        ]);
    }

    public function destroyScript(): JsonResponse
    {
        $deleted = $this->deleteKeys([$this->scriptTemplateKey()]);

        return response()->json([
            'message' => $deleted > 0
                ? 'Cache do script removido com sucesso.'
                : 'Nenhum cache do script foi encontrado para remoção.',
            'deleted' => $deleted,
        ]);
    }

    public function campaigns(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sortBy' => ['nullable', 'string'],
            'descending' => ['nullable'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $rows = collect($this->scanKeys($this->campaignKeyPrefix() . '*'))
            ->map(fn (string $key) => $this->buildCampaignRow($key))
            ->filter()
            ->filter(function (array $row) use ($validated) {
                $search = trim((string) ($validated['search'] ?? ''));
                if ($search === '') {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', [
                    (string) ($row['campaign_name'] ?? ''),
                    (string) ($row['campaign_code'] ?? ''),
                    (string) ($row['allowed_origin'] ?? ''),
                    (string) ($row['key'] ?? ''),
                ]));

                return str_contains($haystack, mb_strtolower($search));
            })
            ->values();

        $sorted = $this->sortRows(
            $rows,
            (string) ($validated['sortBy'] ?? 'campaign_name'),
            filter_var($validated['descending'] ?? false, FILTER_VALIDATE_BOOLEAN),
            [
                'campaign_name' => 'campaign_name',
                'campaign_code' => 'campaign_code',
                'allowed_origin' => 'allowed_origin',
                'ttl_seconds' => 'ttl_seconds',
                'memory_bytes' => 'memory_bytes',
                'payload_size_bytes' => 'payload_size_bytes',
            ]
        );

        return response()->json($this->paginateRows(
            $sorted,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 10)
        ));
    }

    public function destroyCampaign(string $cacheId): JsonResponse
    {
        $key = $this->decodeCacheId($cacheId);
        abort_if($key === null, 404);
        abort_if($this->buildCampaignRow($key) === null, 404);

        $deleted = $this->deleteKeys([$key]);

        return response()->json([
            'message' => $deleted > 0
                ? 'Cache da campanha removido com sucesso.'
                : 'Nenhum cache da campanha foi encontrado para remoção.',
            'deleted' => $deleted,
        ]);
    }

    public function bulkDestroyCampaigns(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cache_ids' => ['nullable', 'array'],
            'cache_ids.*' => ['string'],
            'mode' => ['nullable', 'string', 'in:selected,all'],
        ]);

        $keys = $this->resolveBulkCampaignKeys($data);
        $deleted = $this->deleteKeys($keys);

        return response()->json([
            'message' => $deleted > 0
                ? 'Caches de campanha removidos com sucesso.'
                : 'Nenhum cache de campanha foi encontrado para remoção.',
            'deleted' => $deleted,
        ]);
    }

    public function pageviews(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sortBy' => ['nullable', 'string'],
            'descending' => ['nullable'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $rows = collect($this->scanKeys($this->pageviewKeyPrefix() . '*'))
            ->map(fn (string $key) => $this->buildPageviewRow($key))
            ->filter()
            ->filter(function (array $row) use ($validated) {
                $search = trim((string) ($validated['search'] ?? ''));
                if ($search === '') {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', [
                    (string) ($row['campaign_name'] ?? ''),
                    (string) ($row['campaign_code'] ?? ''),
                    (string) ($row['pageview_id'] ?? ''),
                    (string) ($row['visitor_id'] ?? ''),
                    (string) ($row['page_url'] ?? ''),
                    (string) ($row['key'] ?? ''),
                ]));

                return str_contains($haystack, mb_strtolower($search));
            })
            ->values();

        $sorted = $this->sortRows(
            $rows,
            (string) ($validated['sortBy'] ?? 'occurred_at_sort'),
            filter_var($validated['descending'] ?? true, FILTER_VALIDATE_BOOLEAN),
            [
                'pageview_id' => 'pageview_id',
                'campaign_name' => 'campaign_name',
                'visitor_id' => 'visitor_id',
                'occurred_at' => 'occurred_at_sort',
                'ttl_seconds' => 'ttl_seconds',
                'memory_bytes' => 'memory_bytes',
                'payload_size_bytes' => 'payload_size_bytes',
            ]
        );

        return response()->json($this->paginateRows(
            $sorted,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 10)
        ));
    }

    public function destroyPageview(string $cacheId): JsonResponse
    {
        $key = $this->decodeCacheId($cacheId);
        abort_if($key === null, 404);
        abort_if($this->buildPageviewRow($key) === null, 404);

        $deleted = $this->deleteKeys($this->resolveRelatedPageviewKeys([$key]));

        return response()->json([
            'message' => $deleted > 0
                ? 'Cache da pageview removido com sucesso.'
                : 'Nenhum cache da pageview foi encontrado para remoção.',
            'deleted' => $deleted,
        ]);
    }

    public function bulkDestroyPageviews(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cache_ids' => ['nullable', 'array'],
            'cache_ids.*' => ['string'],
            'mode' => ['nullable', 'string', 'in:selected,all'],
        ]);

        if (($data['mode'] ?? 'selected') === 'all') {
            $visibleKeys = collect($this->scanKeys($this->pageviewKeyPrefix() . '*'))
                ->filter(fn (string $key) => $this->buildPageviewRow($key) !== null)
                ->values()
                ->all();

            $keys = $this->resolveRelatedPageviewKeys($visibleKeys);
        } else {
            $decodedKeys = collect($data['cache_ids'] ?? [])
                ->map(fn (string $cacheId) => $this->decodeCacheId($cacheId))
                ->filter(fn (?string $key) => is_string($key) && $this->buildPageviewRow($key) !== null)
                ->filter()
                ->values()
                ->all();

            $keys = $this->resolveRelatedPageviewKeys($decodedKeys);
        }

        $deleted = $this->deleteKeys($keys);

        return response()->json([
            'message' => $deleted > 0
                ? 'Caches de pageview removidos com sucesso.'
                : 'Nenhum cache de pageview foi encontrado para remoção.',
            'deleted' => $deleted,
        ]);
    }

    public function flushAll(): JsonResponse
    {
        $deleted = $this->deleteKeys($this->scanKeys($this->trackingPrefix() . ':*'));

        return response()->json([
            'message' => $deleted > 0
                ? 'Todas as chaves de tracking foram removidas com sucesso.'
                : 'Nenhuma chave de tracking foi encontrada para remoção.',
            'deleted' => $deleted,
        ]);
    }

    protected function buildCampaignRow(string $key): ?array
    {
        $payload = $this->decodeRedisJson($this->readKeyPayload($key));
        if (!is_array($payload)) {
            return null;
        }

        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId > 0 && $userId !== (int) auth()->id()) {
            return null;
        }

        $ttl = $this->readTtl($key);
        $memoryBytes = $this->readMemoryUsage($key);
        $rawPayload = $this->readKeyPayload($key);
        $payloadSizeBytes = $rawPayload !== null ? strlen($rawPayload) : 0;

        return [
            'cache_id' => $this->encodeCacheId($key),
            'key' => $key,
            'campaign_id' => (int) ($payload['id'] ?? 0),
            'campaign_name' => (string) ($payload['name'] ?? '-'),
            'campaign_code' => (string) ($payload['code'] ?? '-'),
            'allowed_origin' => (string) ($payload['allowed_origin'] ?? '-'),
            'user_id' => $userId,
            'ttl_seconds' => $ttl,
            'ttl_label' => $this->formatTtlLabel($ttl),
            'memory_bytes' => $memoryBytes,
            'memory_label' => $this->formatBytes($memoryBytes),
            'payload_size_bytes' => $payloadSizeBytes,
            'payload_size_label' => $this->formatBytes($payloadSizeBytes),
            'redis_type' => $this->readRedisType($key),
            'payload' => $payload,
        ];
    }

    protected function buildPageviewRow(string $key): ?array
    {
        $rawPayload = $this->readKeyPayload($key);
        $payload = $this->decodeRedisJson($rawPayload);
        if (!is_array($payload)) {
            return null;
        }

        $campaignPayload = is_array($payload['campanha'] ?? null) ? $payload['campanha'] : [];
        $pageviewPayload = is_array($payload['pageview'] ?? null) ? $payload['pageview'] : [];
        $timingPayload = is_array($payload['timing'] ?? null) ? $payload['timing'] : [];

        $userId = (int) ($campaignPayload['user_id'] ?? $pageviewPayload['user_id'] ?? 0);
        if ($userId > 0 && $userId !== (int) auth()->id()) {
            return null;
        }

        $ttl = $this->readTtl($key);
        $memoryBytes = $this->readMemoryUsage($key);
        $payloadSizeBytes = $rawPayload !== null ? strlen($rawPayload) : 0;
        $occurredAt = $pageviewPayload['occurred_at'] ?? $pageviewPayload['created_at'] ?? null;

        return [
            'cache_id' => $this->encodeCacheId($key),
            'key' => $key,
            'pageview_id' => (int) ($pageviewPayload['id'] ?? 0),
            'campaign_id' => (int) ($campaignPayload['id'] ?? $pageviewPayload['campaign_id'] ?? 0),
            'campaign_name' => (string) ($campaignPayload['nome'] ?? '-'),
            'campaign_code' => (string) ($campaignPayload['code'] ?? '-'),
            'visitor_id' => (int) ($pageviewPayload['visitor_id'] ?? 0),
            'page_url' => (string) ($pageviewPayload['url'] ?? ''),
            'occurred_at' => $this->formatDateTime($occurredAt),
            'occurred_at_sort' => $occurredAt ? (string) $occurredAt : '',
            'last_collect_at' => $this->formatMillisDateTime($timingPayload['last_collect_at_ms'] ?? null),
            'last_hit_at' => $this->formatMillisDateTime($timingPayload['last_hit_at_ms'] ?? null),
            'ttl_seconds' => $ttl,
            'ttl_label' => $this->formatTtlLabel($ttl),
            'memory_bytes' => $memoryBytes,
            'memory_label' => $this->formatBytes($memoryBytes),
            'payload_size_bytes' => $payloadSizeBytes,
            'payload_size_label' => $this->formatBytes($payloadSizeBytes),
            'redis_type' => $this->readRedisType($key),
            'payload' => $payload,
        ];
    }

    protected function resolveBulkCampaignKeys(array $data): array
    {
        if (($data['mode'] ?? 'selected') === 'all') {
            return collect($this->scanKeys($this->campaignKeyPrefix() . '*'))
                ->filter(fn (string $key) => $this->buildCampaignRow($key) !== null)
                ->values()
                ->all();
        }

        return collect($data['cache_ids'] ?? [])
            ->map(fn (string $cacheId) => $this->decodeCacheId($cacheId))
            ->filter(fn (?string $key) => is_string($key) && $this->buildCampaignRow($key) !== null)
            ->filter()
            ->values()
            ->all();
    }

    protected function resolveRelatedPageviewKeys(array $pageviewKeys): array
    {
        $relatedKeys = [];

        foreach ($pageviewKeys as $key) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $relatedKeys[] = $key;
            $payload = $this->decodeRedisJson($this->readKeyPayload($key));
            if (!is_array($payload)) {
                continue;
            }

            $campaignPayload = is_array($payload['campanha'] ?? null) ? $payload['campanha'] : [];
            $pageviewPayload = is_array($payload['pageview'] ?? null) ? $payload['pageview'] : [];

            $campaignCode = trim((string) ($campaignPayload['code'] ?? ''));
            $campaignId = (int) ($campaignPayload['id'] ?? $pageviewPayload['campaign_id'] ?? 0);
            $pageviewId = (int) ($pageviewPayload['id'] ?? 0);
            $userId = (int) ($campaignPayload['user_id'] ?? $pageviewPayload['user_id'] ?? 0);
            $visitorId = (int) ($pageviewPayload['visitor_id'] ?? 0);

            if ($userId > 0 && $campaignCode !== '' && $pageviewId > 0) {
                $userCode = app(HashidService::class)->encode($userId);
                $pageviewCode = app(HashidService::class)->encode($pageviewId);
                $relatedKeys[] = $this->pageviewKeyPrefix() . $userCode . ':' . $campaignCode . ':' . $pageviewCode;
            }

            if ($userId > 0 && $campaignCode !== '' && $visitorId > 0) {
                $userCode = app(HashidService::class)->encode($userId);
                $visitorCode = app(HashidService::class)->encode($visitorId);
                $relatedKeys[] = $this->lastCollectKeyPrefix() . $userCode . ':' . $campaignCode . ':' . $visitorCode;
            }

            if ($campaignId > 0 && $visitorId > 0) {
                $relatedKeys[] = $this->hitGateKeyPrefix() . $campaignId . ':' . $visitorId;
            }
        }

        return array_values(array_unique($relatedKeys));
    }

    protected function paginateRows(Collection $rows, int $page, int $perPage): array
    {
        $page = max($page, 1);
        $perPage = min(max($perPage, 5), 100);
        $total = $rows->count();
        $offset = ($page - 1) * $perPage;

        return [
            'data' => $rows->slice($offset, $perPage)->values()->all(),
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max((int) ceil($total / $perPage), 1),
            'from' => $total > 0 ? $offset + 1 : null,
            'to' => $total > 0 ? min($offset + $perPage, $total) : null,
        ];
    }

    protected function sortRows(Collection $rows, string $sortBy, bool $descending, array $allowedColumns): Collection
    {
        $column = $allowedColumns[$sortBy] ?? array_values($allowedColumns)[0] ?? $sortBy;

        return $rows->sort(function (array $left, array $right) use ($column, $descending) {
            $leftValue = $left[$column] ?? null;
            $rightValue = $right[$column] ?? null;

            if (is_numeric($leftValue) && is_numeric($rightValue)) {
                $comparison = $leftValue <=> $rightValue;
            } else {
                $comparison = strcmp(
                    mb_strtolower((string) $leftValue),
                    mb_strtolower((string) $rightValue)
                );
            }

            return $descending ? ($comparison * -1) : $comparison;
        })->values();
    }

    protected function summarizeMemoryUsage(array $keys): array
    {
        $totalMemoryBytes = 0;
        $memoryAvailable = true;
        $totalPayloadBytes = 0;

        foreach ($keys as $key) {
            $memory = $this->readMemoryUsage($key);
            if ($memory === null) {
                $memoryAvailable = false;
            } else {
                $totalMemoryBytes += $memory;
            }

            $payload = $this->readKeyPayload($key);
            if ($payload !== null) {
                $totalPayloadBytes += strlen($payload);
            }
        }

        return [
            'total_memory_bytes' => $memoryAvailable ? $totalMemoryBytes : null,
            'total_memory_label' => $memoryAvailable ? $this->formatBytes($totalMemoryBytes) : 'Indisponível',
            'total_payload_bytes' => $totalPayloadBytes,
            'total_payload_label' => $this->formatBytes($totalPayloadBytes),
            'memory_command_available' => $memoryAvailable,
        ];
    }

    protected function scanKeys(string $pattern): array
    {
        $keys = [];
        $cursor = '0';

        do {
            $response = $this->trackingRedis()->command('scan', [$cursor, 'MATCH', $pattern, 'COUNT', 200]);
            if (!is_array($response) || count($response) < 2) {
                break;
            }

            $cursor = (string) ($response[0] ?? '0');
            $batch = $response[1] ?? [];

            if (is_array($batch)) {
                foreach ($batch as $key) {
                    if (is_string($key) && $key !== '') {
                        $keys[] = $key;
                    }
                }
            }
        } while ($cursor !== '0');

        return array_values(array_unique($keys));
    }

    protected function deleteKeys(array $keys): int
    {
        $keys = array_values(array_unique(array_filter($keys, fn ($key) => is_string($key) && $key !== '')));
        if ($keys === []) {
            return 0;
        }

        $deleted = 0;

        foreach (array_chunk($keys, 500) as $chunk) {
            $deleted += (int) $this->trackingRedis()->del(...$chunk);
        }

        return $deleted;
    }

    protected function readKeyPayload(string $key): ?string
    {
        $value = $this->trackingRedis()->get($key);

        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    protected function readTtl(string $key): int
    {
        return (int) $this->trackingRedis()->ttl($key);
    }

    protected function readRedisType(string $key): string
    {
        $type = $this->trackingRedis()->type($key);

        if (is_string($type)) {
            return $type;
        }

        $map = [
            0 => 'none',
            1 => 'string',
            2 => 'set',
            3 => 'list',
            4 => 'zset',
            5 => 'hash',
            6 => 'stream',
        ];

        return $map[(int) $type] ?? 'unknown';
    }

    protected function readMemoryUsage(string $key): ?int
    {
        try {
            $usage = $this->trackingRedis()->command('memory', ['usage', $key]);
            return is_numeric($usage) ? (int) $usage : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function formatTtlLabel(int $ttl): string
    {
        if ($ttl === -2) {
            return 'Expirada';
        }

        if ($ttl === -1) {
            return 'Sem expiração';
        }

        if ($ttl < 60) {
            return $ttl . ' s';
        }

        if ($ttl < 3600) {
            return number_format($ttl / 60, 1, ',', '.') . ' min';
        }

        if ($ttl < 86400) {
            return number_format($ttl / 3600, 1, ',', '.') . ' h';
        }

        return number_format($ttl / 86400, 1, ',', '.') . ' d';
    }

    protected function formatBytes(?int $bytes): string
    {
        if ($bytes === null || $bytes < 0) {
            return 'Indisponível';
        }

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes;
        $unitIndex = -1;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        if ($unitIndex < 0) {
            return $bytes . ' B';
        }

        return number_format($value, 2, ',', '.') . ' ' . $units[$unitIndex];
    }

    protected function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value, 'UTC')
                ->setTimezone('America/Sao_Paulo')
                ->format('d/m/Y H:i:s');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    protected function formatMillisDateTime(mixed $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        try {
            return Carbon::createFromTimestampUTC(((float) $value) / 1000)
                ->setTimezone('America/Sao_Paulo')
                ->format('d/m/Y H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function decodeRedisJson(?string $payload): ?array
    {
        if ($payload === null || trim($payload) === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    protected function encodeCacheId(string $key): string
    {
        return rtrim(strtr(base64_encode($key), '+/', '-_'), '=');
    }

    protected function decodeCacheId(string $cacheId): ?string
    {
        $normalized = strtr($cacheId, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        if (!str_starts_with($decoded, $this->trackingPrefix() . ':')) {
            return null;
        }

        return $decoded;
    }

    protected function filterKeysByPrefix(array $keys, string $prefix): array
    {
        return array_values(array_filter(
            $keys,
            fn (string $key) => str_starts_with($key, $prefix)
        ));
    }

    protected function trackingRedis(): \Illuminate\Redis\Connections\Connection
    {
        return Redis::connection($this->trackingConnectionName());
    }

    protected function trackingConnectionName(): string
    {
        return (string) config('tracking.redis.connection', 'tracking');
    }

    protected function trackingPrefix(): string
    {
        return trim((string) config('tracking.redis.prefix', 'tracking'));
    }

    protected function campaignKeyPrefix(): string
    {
        return $this->trackingPrefix() . ':campaign:';
    }

    protected function pageviewKeyPrefix(): string
    {
        return $this->trackingPrefix() . ':pv:';
    }

    protected function lastCollectKeyPrefix(): string
    {
        return $this->trackingPrefix() . ':last:';
    }

    protected function hitGateKeyPrefix(): string
    {
        return $this->trackingPrefix() . ':hit_gate:';
    }

    protected function scriptTemplateKey(): string
    {
        return $this->trackingPrefix() . ':script:template:v1';
    }
}
