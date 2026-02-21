<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ClickhouseShowTest extends Command
{
    protected $signature = 'clickhouse:show-test
        {--host= : Override CLICKHOUSE_HOST}
        {--port= : Override CLICKHOUSE_PORT (default 8123)}
        {--user= : Override CLICKHOUSE_USER (default default)}
        {--pass= : Override CLICKHOUSE_PASSWORD}
        {--db=demo : Nome do database}
        {--table=events : Nome da tabela}
        {--limit=50 : Limite de linhas}';

    protected $description = 'Busca e mostra dados do ClickHouse via HTTP (8123)';

    public function handle(): int
    {
        $host  = $this->option('host') ?? env('CLICKHOUSE_HOST', '127.0.0.1');
        $port  = (int) ($this->option('port') ?? env('CLICKHOUSE_PORT', 8123));
        $user  = $this->option('user') ?? env('CLICKHOUSE_USER', 'default');
        $pass  = $this->option('pass') ?? env('CLICKHOUSE_PASSWORD', '');
        $db    = $this->option('db') ?? 'demo';
        $table = $this->option('table') ?? 'events';
        $limit = (int) ($this->option('limit') ?? 50);

        $baseUrl = "http://{$host}:{$port}/";

        $sql = <<<SQL
                SELECT
                    id, name, created_at
                FROM {$db}.{$table}
                ORDER BY id
                LIMIT {$limit}
                FORMAT PrettyCompact;
                SQL;

        $response = Http::withBasicAuth($user, $pass)
            ->timeout(20)
            ->withHeaders(['Content-Type' => 'text/plain; charset=utf-8'])
            ->withBody($sql, 'text/plain')
            ->post($baseUrl);

        if (!$response->successful()) {
            $this->error("Erro ao consultar (HTTP {$response->status()}):");
            $this->line($response->body());
            return self::FAILURE;
        }

        $this->line($response->body());
        return self::SUCCESS;
    }
}
