<?php

namespace App\Support\Tracking;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use JShrink\Minifier;
use Throwable;

/**
 * Centraliza utilitarios compartilhados entre os fluxos de tracking.
 *
 * Esta classe concentra apenas responsabilidades reutilizaveis:
 * - parsing do token composto usado no endpoint do script;
 * - geracao de assinaturas HMAC de collect/event;
 * - carregamento, minificacao e cache do template base do script.js.
 */
class TrackingScriptHelper
{
    /**
     * Converte o token composto `user-campaign` em seus dois identificadores.
     *
     * Retorna strings vazias quando o formato nao bate com o esperado, para o
     * controller tratar o caso invalido sem levantar excecao.
     *
     * @return array{0:string,1:string}
     */
    public function parseComposedTrackingCode(string $composedCode): array
    {
        if ($composedCode === '') {
            return ['', ''];
        }

        $parts = explode('-', $composedCode, 2);
        if (count($parts) !== 2) {
            return ['', ''];
        }

        $userCode = trim((string) ($parts[0] ?? ''));
        $campaignCode = trim((string) ($parts[1] ?? ''));
        if ($userCode === '' || $campaignCode === '') {
            return ['', ''];
        }

        return [$userCode, $campaignCode];
    }

    /**
     * Assina o payload do collect para impedir adulteracao do request no cliente.
     */
    public function buildTrackingSignature(string $userCode, string $campaignCode, int $authTs, string $authNonce): string
    {
        $payload = implode('|', [$userCode, $campaignCode, $authTs, $authNonce]);
        $secret = (string) config('app.tracking_signature_secret', '');

        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Assina o payload do endpoint de eventos usando o identificador da pageview.
     */
    public function buildEventSignature(string $userCode, string $campaignCode, string $pageviewCode): string
    {
        $payload = implode('|', [$userCode, $campaignCode, $pageviewCode]);
        $secret = (string) config('app.tracking_signature_secret', '');

        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Carrega o template base do script, aplicando cache em Redis para evitar
     * leitura/minificacao repetida a cada requisicao.
     */
    public function resolveTrackingScriptTemplate(): ?string
    {
        $cacheKey = $this->trackingScriptTemplateCacheKey();
        $redis = $this->trackingRedis();

        try {
            $cached = $redis->get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            $template = $this->loadAndMinifyTrackingScriptTemplate();
            if ($template !== null && $template !== '') {
                $redis->setex($cacheKey, $this->trackingScriptTemplateTtlSeconds(), $template);
            }

            return $template;
        } catch (Throwable $e) {
            Log::channel($this->trackingLogChannel('tracking_collect'))->warning(
                'Unable to load tracking script template from tracking Redis.',
                ['error' => $e->getMessage()]
            );
        }

        return $this->loadAndMinifyTrackingScriptTemplate();
    }

    /**
     * Lê o arquivo-fonte do snippet e tenta minificar antes de enviar ao cliente.
     *
     * Se a minificacao falhar, o conteudo original ainda e devolvido para nao
     * indisponibilizar o endpoint por causa de otimizacao.
     */
    protected function loadAndMinifyTrackingScriptTemplate(): ?string
    {
        $path = resource_path('views/tracking/script.js');

        if (!File::exists($path)) {
            return null;
        }

        $js = File::get($path);

        try {
            return Minifier::minify($js, ['flaggedComments' => false]);
        } catch (Throwable $e) {
            Log::channel($this->trackingLogChannel('tracking_collect'))->warning(
                'Unable to minify tracking script template. Falling back to the original asset.',
                ['error' => $e->getMessage()]
            );

            return $js;
        }
    }

    /**
     * Mantem uma chave fixa e versionada para permitir invalidacao simples do cache.
     */
    protected function trackingScriptTemplateCacheKey(): string
    {
        return $this->trackingPrefix() . ':script:template:v1';
    }

    /**
     * Controla por configuracao quanto tempo o template minificado fica em cache.
     */
    protected function trackingScriptTemplateTtlSeconds(): int
    {
        return max((int) config('tracking.redis.script_template_ttl_seconds', 2592000), 60);
    }

    protected function trackingRedis(): \Illuminate\Redis\Connections\Connection
    {
        return Redis::connection((string) config('tracking.redis.connection', 'tracking'));
    }

    protected function trackingPrefix(): string
    {
        return trim((string) config('tracking.redis.prefix', 'tracking'));
    }

    protected function trackingLogsEnabled(): bool
    {
        return (bool) config('tracking.logs.enabled', true);
    }

    protected function trackingLogChannel(string $channel): string
    {
        return $this->trackingLogsEnabled() ? $channel : 'null';
    }
}
