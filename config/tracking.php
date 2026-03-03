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
        'prefix' => env('TRACKING_REDIS_PREFIX', 'tc'),
        // TTL do contexto da campanha (chave tracking:campaign:{campaign_id}).
        'campaign_ttl_seconds' => (int) env('TRACKING_CAMPAIGN_TTL_SECONDS', 86400),

        /*
        É o tempo de vida do contexto da pageview no Redis.
        Ele define por quanto tempo a chave tipo tracking:pv:... fica armazenada.
        Esse contexto guarda os dados da pageview já criada para reutilização posterior, principalmente no fluxo de event e em partes do collect.
        Se esse TTL expirar, o backend perde o contexto em cache daquela pageview, mesmo que o registro continue no banco.
        */
        'pageview_ttl_seconds' => (int) env('TRACKING_PAGEVIEW_TTL_SECONDS', 86400),

        // TTL do template JS minificado usado pelo endpoint /api/tracking/script.js.
        'script_template_ttl_seconds' => (int) env('TRACKING_SCRIPT_TEMPLATE_TTL_SECONDS', 2592000),
    ],
    'collect' => [
        /*
         É a janela de deduplicação do collect.
         Ela define por quanto tempo um novo collect, com o mesmo visitante e campanha, pode reaproveitar a mesma pageview em vez de criar outra.
         Isso serve para evitar nova pageview em refresh, navegação imediata ou múltiplos collects muito próximos.
        */
        'dedup_window_seconds' => (int) env('TRACKING_DEDUP_WINDOW_SECONDS', 8400),
        
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
