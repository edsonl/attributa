<?php

return [
    'logs' => [
        // Liga/desliga logs do tracking (collect/event) globalmente.
        // Padrao ativado; se TRACKING_LOGS_ENABLED vier no .env, ele prevalece.
        'enabled' => filter_var(env('TRACKING_LOGS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    ],
    'redis' => [
        // Conexao Redis dedicada para tracking. Por padrao usa DB 3 (ver config/database.php).
        'connection' => env('TRACKING_REDIS_CONNECTION', 'tracking'),
        // Prefixo de namespace para isolamento de chaves do tracking.
        'prefix' => env('TRACKING_REDIS_PREFIX', 'tracking'),
        // TTL do contexto da campanha (chave tracking:campaign:{campaign_id}).
        'campaign_ttl_seconds' => (int) env('TRACKING_CAMPAIGN_TTL_SECONDS', 86400),
        // TTL do contexto da pageview e ponte de ultimo collect.
        'pageview_ttl_seconds' => (int) env('TRACKING_PAGEVIEW_TTL_SECONDS', 86400),
    ],
    'collect' => [
        // Janela para reuso da mesma pageview em collects repetidos.
        'dedup_window_seconds' => (int) env('TRACKING_DEDUP_WINDOW_SECONDS', 86400),
        // Intervalo minimo entre hits aceitos para evitar inflacao por F5.
        'min_hit_interval_seconds' => (int) env('TRACKING_MIN_HIT_INTERVAL_SECONDS', 30),
        // TTL da chave de bloqueio de hit rapido (tracking:hit_gate:{campaign_id}:{visitor_id}).
        'hit_gate_ttl_seconds' => (int) env('TRACKING_HIT_GATE_TTL_SECONDS', 90),
    ],
];
