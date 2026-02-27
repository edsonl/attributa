<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class TrackingRedisCommand extends Command
{
    protected $aliases = ['tracking:redis'];

    protected $signature = 'tracking:campaign
        {action : Acao: list|count|show|clear}
        {--type=all : Tipo de chave: campaign|pv|last|hit_gate|all}
        {--key= : Chave completa para acao show}
        {--pattern= : Pattern customizado (sobrescreve o tipo)}
        {--limit= : Limite de registros na listagem (padrao: 10)}
        {--cursor=0 : Cursor inicial para listagem}
        {--scan-count=500 : Hint de volume por iteracao do SCAN}
        {--force : Necessario para acao clear}
        {--json : Saida em JSON}';

    protected $description = 'Inspeciona e gerencia chaves Redis do tracking (list, count, show, clear)';

    private const TYPES = ['campaign', 'pv', 'last', 'hit_gate', 'all'];

    public function handle(): int
    {
        $action = strtolower(trim((string) $this->argument('action')));
        $type = strtolower(trim((string) $this->option('type')));
        $scanCount = max((int) $this->option('scan-count'), 10);

        if (!in_array($action, ['list', 'count', 'show', 'clear'], true)) {
            $this->error('Acao invalida. Use: list, count, show ou clear.');

            return self::FAILURE;
        }

        if (!in_array($type, self::TYPES, true)) {
            $this->error('Tipo invalido. Use: campaign, pv, last, hit_gate ou all.');

            return self::FAILURE;
        }

        $redis = Redis::connection((string) config('tracking.redis.connection', 'tracking'));
        $patternsByType = $this->resolvePatternsByType($type, (string) $this->option('pattern'));

        return match ($action) {
            'list' => $this->handleList($redis, $patternsByType, $scanCount),
            'count' => $this->handleCount($redis, $patternsByType, $scanCount),
            'show' => $this->handleShow($redis),
            'clear' => $this->handleClear($redis, $patternsByType, $scanCount),
            default => self::FAILURE,
        };
    }

    protected function handleList($redis, array $patternsByType, int $scanCount): int
    {
        $limitOption = $this->option('limit');
        $limit = ($limitOption === null || trim((string) $limitOption) === '')
            ? 10
            : max((int) $limitOption, 1);
        $startCursor = max((int) $this->option('cursor'), 0);
        $remaining = $limit;
        $rows = [];

        foreach ($patternsByType as $type => $pattern) {
            if ($remaining <= 0) {
                break;
            }

            [$keys] = $this->scanKeys(
                $redis,
                $pattern,
                $scanCount,
                $remaining,
                $startCursor
            );

            foreach ($keys as $key) {
                $value = $redis->get($key);
                $rows[] = [
                    'type' => $type,
                    'key' => $key,
                    'ttl' => (int) $redis->ttl($key),
                    'bytes' => is_string($value) ? strlen($value) : 0,
                    'preview' => $this->previewValue($value),
                ];
            }

            $remaining = $limit - count($rows);
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($rows === []) {
            $this->warn('Nenhuma chave encontrada para os filtros informados.');

            return self::SUCCESS;
        }

        $this->table(['Type', 'Key', 'TTL', 'Bytes', 'Preview'], $rows);
        $this->line('Total listado: ' . count($rows));

        return self::SUCCESS;
    }

    protected function handleCount($redis, array $patternsByType, int $scanCount): int
    {
        $rows = [];
        $total = 0;

        foreach ($patternsByType as $type => $pattern) {
            [$keys, $count] = $this->scanKeys($redis, $pattern, $scanCount);
            $rows[] = [
                'type' => $type,
                'pattern' => $pattern,
                'count' => $count,
            ];
            $total += $count;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'total' => $total,
                'by_type' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(['Type', 'Pattern', 'Count'], $rows);
        $this->line('Total geral: ' . $total);

        return self::SUCCESS;
    }

    protected function handleShow($redis): int
    {
        $key = trim((string) $this->option('key'));
        if ($key === '') {
            $this->error('Informe --key para acao show.');

            return self::FAILURE;
        }

        if (!$redis->exists($key)) {
            $this->warn('Chave nao encontrada: ' . $key);

            return self::SUCCESS;
        }

        $value = $redis->get($key);
        $decoded = $this->decodeJson($value);
        $payload = [
            'key' => $key,
            'ttl' => (int) $redis->ttl($key),
            'bytes' => is_string($value) ? strlen($value) : 0,
            'value' => $decoded ?? $value,
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Key: ' . $payload['key']);
        $this->line('TTL: ' . $payload['ttl']);
        $this->line('Bytes: ' . $payload['bytes']);
        $this->line('Value:');
        $this->line((string) json_encode($payload['value'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    protected function handleClear($redis, array $patternsByType, int $scanCount): int
    {
        if (!(bool) $this->option('force')) {
            $this->warn('Acao clear exige --force.');

            return self::FAILURE;
        }

        $rows = [];
        $totalDeleted = 0;

        foreach ($patternsByType as $type => $pattern) {
            [$keys] = $this->scanKeys($redis, $pattern, $scanCount);
            $deleted = 0;

            foreach (array_chunk($keys, 500) as $chunk) {
                if ($chunk === []) {
                    continue;
                }
                $deleted += (int) $redis->command('del', $chunk);
            }

            $rows[] = [
                'type' => $type,
                'pattern' => $pattern,
                'deleted' => $deleted,
            ];
            $totalDeleted += $deleted;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'deleted_total' => $totalDeleted,
                'by_type' => $rows,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(['Type', 'Pattern', 'Deleted'], $rows);
        $this->line('Total removido: ' . $totalDeleted);

        return self::SUCCESS;
    }

    /**
     * @return array{0: array<int,string>, 1: int}
     */
    protected function scanKeys($redis, string $pattern, int $scanCount, ?int $limit = null, int $startCursor = 0): array
    {
        $cursor = max($startCursor, 0);
        $keys = [];
        $total = 0;

        do {
            $response = $redis->scan($cursor, [
                'match' => $pattern,
                'count' => $scanCount,
            ]);

            if ($response === false || !is_array($response) || count($response) < 2) {
                break;
            }

            $cursor = (int) ($response[0] ?? 0);
            $batch = is_array($response[1] ?? null) ? $response[1] : [];

            foreach ($batch as $key) {
                $total++;
                if ($limit === null || count($keys) < $limit) {
                    $keys[] = (string) $key;
                }
            }

            if ($limit !== null && count($keys) >= $limit) {
                break;
            }
        } while ($cursor !== 0);

        return [$keys, $total];
    }

    /**
     * @return array<string,string>
     */
    protected function resolvePatternsByType(string $type, string $customPattern): array
    {
        if ($customPattern !== '') {
            return [$type === 'all' ? 'custom' : $type => $customPattern];
        }

        $prefix = trim((string) config('tracking.redis.prefix', 'tracking'));
        $base = [
            'campaign' => $prefix . ':campaign:*',
            'pv' => $prefix . ':pv:*',
            'last' => $prefix . ':last:*',
            'hit_gate' => $prefix . ':hit_gate:*',
        ];

        if ($type === 'all') {
            return $base;
        }

        return [$type => $base[$type]];
    }

    protected function previewValue(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }

        $decoded = $this->decodeJson($value);
        $text = $decoded !== null
            ? (string) json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : $value;

        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        if (strlen($text) > 160) {
            return substr($text, 0, 157) . '...';
        }

        return $text;
    }

    protected function decodeJson(mixed $value): ?array
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }
}
