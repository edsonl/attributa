<?php

namespace App\Console\Commands;

use App\Services\ClickhouseHttpService;
use Illuminate\Console\Command;

class ClickhouseDbCreateCommand extends Command
{
    protected $signature = 'clickhouse:db:create';

    protected $description = 'Cria o database e a tabela pageviews no ClickHouse usando o nome definido no env';

    public function handle(ClickhouseHttpService $clickhouse): int
    {
        try {
            $database = $clickhouse->databaseName();
            $db = $clickhouse->quoteIdentifier($database);
            $table = $clickhouse->quoteIdentifier('pageviews');

            $this->line("ClickHouse: {$clickhouse->baseUrl()}");
            $this->line("Database alvo: {$database}");

            $clickhouse->execute("CREATE DATABASE IF NOT EXISTS {$db};");
            $this->info("Database '{$database}' pronto.");

            $createTableSql = <<<SQL
CREATE TABLE IF NOT EXISTS {$db}.{$table}
(
    id UInt64,
    user_id UInt64,
    campaign_id Nullable(UInt64),
    traffic_source_category_id Nullable(UInt64),
    device_category_id Nullable(UInt64),
    browser_id Nullable(UInt64),
    ip_category_id Nullable(UInt64),
    url String,
    landing_url Nullable(String),
    referrer Nullable(String),
    user_agent Nullable(String),
    utm_source Nullable(String),
    utm_medium Nullable(String),
    utm_campaign Nullable(String),
    utm_term Nullable(String),
    utm_content Nullable(String),
    gclid Nullable(String),
    gad_campaignid Nullable(String),
    fbclid Nullable(String),
    ttclid Nullable(String),
    msclkid Nullable(String),
    wbraid Nullable(String),
    gbraid Nullable(String),
    traffic_source_reason Nullable(String),
    device_type Nullable(String),
    device_brand Nullable(String),
    device_model Nullable(String),
    os_name Nullable(String),
    os_version Nullable(String),
    browser_name Nullable(String),
    browser_version Nullable(String),
    screen_width Nullable(UInt32),
    screen_height Nullable(UInt32),
    viewport_width Nullable(UInt32),
    viewport_height Nullable(UInt32),
    device_pixel_ratio Nullable(Decimal(6, 2)),
    platform Nullable(String),
    language Nullable(String),
    ip Nullable(String),
    country_code Nullable(String),
    country_name Nullable(String),
    region_name Nullable(String),
    city Nullable(String),
    latitude Nullable(Decimal(10, 7)),
    longitude Nullable(Decimal(10, 7)),
    timezone Nullable(String),
    timestamp_ms Nullable(UInt64),
    conversion UInt8 DEFAULT 0,
    created_at DateTime,
    updated_at DateTime
)
ENGINE = MergeTree
PARTITION BY toYYYYMM(created_at)
ORDER BY (id);
SQL;

            $clickhouse->execute($createTableSql);
            $this->info("Tabela '{$database}.pageviews' pronta.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}

