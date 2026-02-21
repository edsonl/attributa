<?php

return [
    'active' => filter_var(env('CLICKHOUSE_ACTIVE', false), FILTER_VALIDATE_BOOL),
    'host' => env('CLICKHOUSE_HOST', '127.0.0.1'),
    'port' => (int) env('CLICKHOUSE_PORT', 8123),
    'user' => env('CLICKHOUSE_USER', 'default'),
    'password' => env('CLICKHOUSE_PASSWORD', ''),
    'database' => env('CLICKHOUSE_DATABASE', 'attributa'),
    'timeout' => (int) env('CLICKHOUSE_TIMEOUT', 20),
];
