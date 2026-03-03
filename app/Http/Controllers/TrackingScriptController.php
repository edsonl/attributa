<?php

namespace App\Http\Controllers;

use App\Services\HashidService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrackingScriptController extends TrackingController
{
    // Retorna o script de acompanhamento.
    public function script(Request $request)
    {
        $log = Log::channel($this->trackingLogChannel('tracking_collect'));

        // Token composto vindo da URL (?c={user_code}-{campaign_code}).
        // esta váriavel ?c é composta por id do usuário e código da campanha - codificados em hashid
        $composedCode = trim((string) $request->query('c'));
        [$userCode, $campaignCode] = $this->trackingScriptHelper->parseComposedTrackingCode($composedCode);

        $decodedUserId = app(HashidService::class)->decode($userCode);
        $decodedCampaignId = app(HashidService::class)->decode($campaignCode);

        $campaign = null;
        if ($decodedUserId && $decodedCampaignId && $composedCode !== '') {
            $campaign = $this->resolveCampaignContext(
                (int) $decodedUserId,
                (int) $decodedCampaignId,
                $campaignCode
            );
        }

        if ($composedCode === '') {
            $log->warning('Composed code not found in request - requisição do arquivo script.js');
        }

        // So entrega o JS quando os tokens batem com uma campanha valida do usuario.
        if (!$campaign) {
            return response()->make(
                'console.error("[Leadnode] Tokens de tracking inválidos");',
                200,
                [
                    'Content-Type'  => 'application/javascript',
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma'        => 'no-cache',
                    'Expires'       => '0',
                ]
            );
        }

        $jsTemplate = $this->trackingScriptHelper->resolveTrackingScriptTemplate();
        if ($jsTemplate === null) {
            return response()->make(
                'console.error("[Leadnode] Script base não encontrado");',
                500,
                ['Content-Type' => 'application/javascript']
            );
        }

        $endpoint = rtrim(config('app.url'), '/') . '/api/tracking/collect';
        $eventEndpoint = rtrim(config('app.url'), '/') . '/api/tracking/event';

        // Gera os campos de autenticacao que o snippet vai reenviar no collect.
        // - auth_ts: timestamp Unix usado para limitar a validade da assinatura.
        // - auth_nonce: valor aleatorio de uso unico para bloquear replay.
        // - auth_sig: HMAC SHA-256 de user_code|campaign_code|auth_ts|auth_nonce.
        //
        // No endpoint /tracking/collect o backend refaz essa mesma assinatura com
        // o segredo interno da aplicacao e compara com a recebida no request.
        // Se qualquer parte do payload for alterada, a assinatura nao confere.
        // Alem disso:
        // - auth_ts precisa estar dentro da janela configurada;
        // - auth_nonce so pode ser aceito uma vez durante essa janela.
        //
        // Com isso o collect rejeita payload adulterado, expirado ou reaproveitado.
        $authTs = time();
        $authNonce = Str::random(24);
        $authSig = $this->trackingScriptHelper->buildTrackingSignature($userCode, $campaignCode, $authTs, $authNonce);

        // O affiliate platform pode definir um mapa no formato
        // ['utm_source' => 'source', 'utm_campaign' => 'campaign', 'sub1'=>xkjdjdhd].
        // Aqui o script precisa apenas da lista de chaves de entrada aceitas,
        // porque sao esses nomes que ele vai procurar na URL atual.
        $trackingParamMapping = is_array($campaign['tracking_param_mapping'] ?? null)
            ? $campaign['tracking_param_mapping']
            : [];
        $trackingParamKeys = is_array($trackingParamMapping)
            ? array_values(array_filter(array_map(
                fn ($k) => trim((string) $k),
                array_keys($trackingParamMapping)
            )))
            : [];

        $replacements = [
            "'{ENDPOINT}'" => json_encode($endpoint),
            "'{EVENT_ENDPOINT}'" => json_encode($eventEndpoint),
            "'{USER_CODE}'" => json_encode($userCode),
            "'{CAMPAIGN_CODE}'" => json_encode($campaignCode),
            "'{AUTH_TS}'" => json_encode($authTs),
            "'{AUTH_NONCE}'" => json_encode($authNonce),
            "'{AUTH_SIG}'" => json_encode($authSig),
            "'{TRACKING_PARAM_KEYS}'" => json_encode($trackingParamKeys),
        ];

        $js = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $jsTemplate
        );

        return response()->make(
            $js,
            200,
            [
                'Content-Type'  => 'application/javascript',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma'        => 'no-cache',
                'Expires'       => '0',
            ]
        );
    }
}
