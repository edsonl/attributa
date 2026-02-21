<?php

namespace App\Console\Commands;

use App\Services\ClickhouseHttpService;
use Illuminate\Console\Command;

class ClickhouseDbDeleteCommand extends Command
{
    protected $signature = 'clickhouse:db:delete {--force : Executa sem pedir confirmação}';

    protected $description = 'Exclui o database configurado no env (inclui as tabelas internas dele)';

    public function handle(ClickhouseHttpService $clickhouse): int
    {
        try {
            $database = $clickhouse->databaseName();
            $db = $clickhouse->quoteIdentifier($database);

            if (!$this->option('force')) {
                $confirmed = $this->confirm("Confirma excluir o database '{$database}' no ClickHouse?", false);
                if (!$confirmed) {
                    $this->warn('Operação cancelada.');
                    return self::SUCCESS;
                }
            }

            $clickhouse->execute("DROP DATABASE IF EXISTS {$db};");
            $this->info("Database '{$database}' excluído.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}

