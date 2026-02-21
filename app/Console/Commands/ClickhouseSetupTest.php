<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ClickhouseSetupTest extends Command
{
    protected $signature = 'clickhouse:setup-test
        {--host= : Override CLICKHOUSE_HOST}
        {--port= : Override CLICKHOUSE_PORT (default 8123)}
        {--user= : Override CLICKHOUSE_USER (default default)}
        {--pass= : Override CLICKHOUSE_PASSWORD}
        {--db=demo : Nome do database}
        {--table=events : Nome da tabela}';

    protected $description = 'Cria database, tabela e insere dados no ClickHouse via HTTP (8123)';

    public function handle(): int
    {
        $host  = $this->option('host') ?? env('CLICKHOUSE_HOST', '127.0.0.1');
        $port  = (int) ($this->option('port') ?? env('CLICKHOUSE_PORT', 8123));
        $user  = $this->option('user') ?? env('CLICKHOUSE_USER', 'default');
        $pass  = $this->option('pass') ?? env('CLICKHOUSE_PASSWORD', '');
        $db    = $this->option('db') ?? 'demo';
        $table = $this->option('table') ?? 'events';

        $baseUrl = "http://{$host}:{$port}/";

        $sendSql = function (string $sql, string $label) use ($baseUrl, $user, $pass) {
            $response = Http::withBasicAuth($user, $pass)
                ->timeout(20)
                ->withHeaders([
                    'Content-Type' => 'text/plain; charset=utf-8',
                ])
                ->withBody($sql, 'text/plain')
                ->post($baseUrl);

            if (!$response->successful()) {
                throw new \RuntimeException(
                    "❌ Falha no ClickHouse ({$label})\n\n" .
                    "SQL:\n{$sql}\n\n" .
                    "HTTP: {$response->status()}\n" .
                    "Resposta:\n{$response->body()}\n"
                );
            }

            return $response->body();
        };

        try {
            $this->info("➡️ ClickHouse: {$baseUrl} (user={$user})");

            // 1) Database
            $this->line("🔧 Criando database '{$db}'...");
            $sendSql("CREATE DATABASE IF NOT EXISTS {$db};", 'CREATE DATABASE');
            $this->info("✅ Database '{$db}' OK");

            // 2) Tabela
            $this->line("🔧 Criando tabela '{$db}.{$table}'...");
            $createTable = <<<SQL
CREATE TABLE IF NOT EXISTS {$db}.{$table}
(
    id UInt64,
    name String,
    created_at DateTime
)
ENGINE = MergeTree
ORDER BY (id);
SQL;
            $sendSql($createTable, 'CREATE TABLE');
            $this->info("✅ Tabela '{$db}.{$table}' OK");

            // 3) Insert (JSONEachRow)
            $this->line("🧾 Inserindo dados de exemplo...");
            $insert = <<<SQL
INSERT INTO {$db}.{$table} FORMAT JSONEachRow
{"id":1,"name":"primeiro","created_at":"2026-02-21 10:40:00"}
{"id":2,"name":"segundo","created_at":"2026-02-21 10:41:00"}
{"id":3,"name":"terceiro","created_at":"2026-02-21 10:42:00"};
SQL;
            // Obs: o ; no fim não atrapalha, mas se quiser pode remover.
            $sendSql($insert, 'INSERT');
            $this->info("✅ Inserts OK");

            // 4) Count (retorna número puro)
            $this->line("🔍 Validando (COUNT)...");
            $count = trim($sendSql("SELECT count() FROM {$db}.{$table};", 'SELECT COUNT'));
            $this->info("✅ Count atual: {$count}");

            $this->newLine();
            $this->info("Próximo comando:");
            $this->line("php artisan clickhouse:show --db={$db} --table={$table}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->newLine();
            $this->warn("Dicas:");
            $this->line("- Confira .env: CLICKHOUSE_HOST/PORT/USER/PASSWORD");
            $this->line("- Rode: php artisan config:clear");
            $this->line("- Teste no servidor: curl -u 'default:senha' 'http://HOST:8123/?query=SELECT+1'");
            return self::FAILURE;
        }
    }
}
