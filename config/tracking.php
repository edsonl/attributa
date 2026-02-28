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
        // TTL do template JS minificado usado pelo endpoint /api/tracking/script.js.
        'script_template_ttl_seconds' => (int) env('TRACKING_SCRIPT_TEMPLATE_TTL_SECONDS', 2592000),
    ],
    'collect' => [
        // Janela para reuso da mesma pageview em collects repetidos.
        'dedup_window_seconds' => (int) env('TRACKING_DEDUP_WINDOW_SECONDS', 86400),
        // Intervalo minimo (em segundos) entre hits aceitos para o mesmo visitante na mesma campanha.
        // Se um novo collect chegar antes desse prazo, nao incrementa campaign_visitors.hits.
        'min_hit_interval_seconds' => (int) env('TRACKING_MIN_HIT_INTERVAL_SECONDS', 30),
        // TTL da chave Redis de bloqueio de hit rapido:
        // tracking:hit_gate:{campaign_id}:{visitor_id}.
        // Essa chave guarda o timestamp do ultimo hit aceito e expira sozinha para nao acumular lixo no Redis.
        // Regra pratica: usar TTL maior que min_hit_interval_seconds (ex.: 2x a 3x).
        'hit_gate_ttl_seconds' => (int) env('TRACKING_HIT_GATE_TTL_SECONDS', 90),
    ],
];
