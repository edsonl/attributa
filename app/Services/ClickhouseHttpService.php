<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ClickhouseHttpService
{
    public function execute(string $sql): string
    {
        $response = Http::withBasicAuth(
            (string) config('clickhouse.user', 'default'),
            (string) config('clickhouse.password', '')
        )
            ->timeout((int) config('clickhouse.timeout', 20))
            ->withHeaders([
                'Content-Type' => 'text/plain; charset=utf-8',
            ])
            ->withBody($sql, 'text/plain')
            ->post($this->baseUrl());

        if (!$response->successful()) {
            throw new RuntimeException(
                'Falha no ClickHouse. HTTP ' . $response->status() . PHP_EOL .
                'SQL: ' . $sql . PHP_EOL .
                'Resposta: ' . $response->body()
            );
        }

        return (string) $response->body();
    }

    public function databaseName(): string
    {
        return (string) config('clickhouse.database', 'attributa');
    }

    public function quoteIdentifier(string $identifier): string
    {
        $value = trim($identifier);
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new RuntimeException("Identificador inválido para ClickHouse: {$identifier}");
        }

        return '`' . $value . '`';
    }

    public function baseUrl(): string
    {
        $host = (string) config('clickhouse.host', '127.0.0.1');
        $port = (int) config('clickhouse.port', 8123);

        return "http://{$host}:{$port}/";
    }
}

