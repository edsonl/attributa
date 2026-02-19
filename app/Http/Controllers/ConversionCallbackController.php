<?php

namespace App\Http\Controllers;

use App\Models\AdsConversion;
use App\Models\Campaign;
use App\Models\Pageview;
use App\Services\HashidService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConversionCallbackController extends Controller
{
    public function handle(Request $request)
    {
        $log = Log::channel('affiliate_platform_callback');

        $log->info('CALLBACK RAW', $request->query());

        $campaignCode = null;
        $userCode = null;
        $userIdFromToken = null;
        $campaignIdFromToken = null;
        $pageviewId = null;
        $pageviewToken = null;

        // 🔎 Testa subid1 → subid5 (com fallback sub1 → sub5)
        for ($i = 1; $i <= 5; $i++) {
            $subField = "subid{$i}";
            $sub = $request->query($subField);

            if (!$sub) {
                $subField = "sub{$i}";
                $sub = $request->query($subField);
            }

            if (!$sub) {
                continue;
            }

            $parts = explode('-', trim((string) $sub), 3);
            if (count($parts) === 3) {
                [$userCode, $campaignCode, $pageviewToken] = $parts;
                $userIdFromToken = $this->resolveUserIdFromToken($userCode);
                $campaignIdFromToken = $this->resolveCampaignIdFromToken($campaignCode);
                $pageviewId = $this->resolvePageviewIdFromToken($pageviewToken);

                $log->info('SUB MATCH', [
                    'source_field'   => $subField,
                    'sub'           => $sub,
                    'user_code'     => $userCode,
                    'user_id'       => $userIdFromToken,
                    'campaign_code' => $campaignCode,
                    'campaign_id'   => $campaignIdFromToken,
                    'pageview_token'=> $pageviewToken,
                    'pageview_id'   => $pageviewId,
                ]);

                if ($userIdFromToken && $campaignIdFromToken && $pageviewId) {
                    break;
                }
            }
        }

        if (!$userIdFromToken || !$campaignIdFromToken || !$pageviewId) {
            $log->warning('Código composto inválido', [
                'query' => $request->query()
            ]);
            return 'ignored';
        }

        // 🔎 Buscar campanha
        $campaign = Campaign::with('conversionGoal')
            ->where('id', $campaignIdFromToken)
            ->where('user_id', $userIdFromToken)
            ->where('code', $campaignCode)
            ->first();

        if (!$campaign) {
            $log->warning('Campanha não encontrada', [
                'campaign_code' => $campaignCode
            ]);
            return 'ignored';
        }

        // 🔎 Buscar pageview
        $pageview = Pageview::query()->find($pageviewId);

        if (!$pageview) {
            $log->warning('Pageview não encontrada', [
                'pageview_id' => $pageviewId,
                'pageview_token' => $pageviewToken,
            ]);
            return 'ignored';
        }

        // Validação forte: o código composto precisa bater com a campanha da própria pageview.
        if ((int) $pageview->campaign_id !== (int) $campaign->id) {
            $log->warning('Pageview não pertence à campanha do callback', [
                'pageview_id' => $pageview->id,
                'pageview_campaign_id' => $pageview->campaign_id,
                'callback_campaign_id' => $campaign->id,
                'callback_campaign_code' => $campaignCode,
                'pageview_campaign_code' => $pageview->campaign_code,
            ]);
            return 'ignored';
        }

        if ((int) $pageview->user_id !== (int) $campaign->user_id) {
            $log->warning('Pageview não pertence ao usuário da campanha do callback', [
                'pageview_id' => $pageview->id,
                'pageview_user_id' => $pageview->user_id,
                'campaign_user_id' => $campaign->user_id,
                'user_code' => $userCode,
            ]);
            return 'ignored';
        }

        $gclid = $pageview->gclid;
        $gbraid = $pageview->gbraid;
        $wbraid = $pageview->wbraid;
        $userAgent = $pageview->user_agent ?: $request->userAgent();
        $ipAddress = $pageview->ip ?: $request->ip();

        $gclid = $gclid ? mb_substr((string) $gclid, 0, 150) : null;
        $gbraid = $gbraid ? mb_substr((string) $gbraid, 0, 150) : null;
        $wbraid = $wbraid ? mb_substr((string) $wbraid, 0, 150) : null;
        $ipAddress = $ipAddress ? mb_substr((string) $ipAddress, 0, 45) : null;

        // ✅ Marca conversão (idempotente)
        if (!$pageview->conversion) {
            $pageview->update(['conversion' => 1]);
        }

        if (!$gclid) {
            $log->warning('Conversão sem GCLID', [
                'pageview_id' => $pageview->id,
                'campaign_id' => $campaign->id,
            ]);
        }

        // 🔁 Verifica duplicidade
        $existingConversion = AdsConversion::where('pageview_id', $pageview->id)
            ->where('campaign_id', $campaign->id)
            ->first();

        if ($existingConversion) {
            $log->info('Conversão já registrada', [
                'pageview_id' => $pageview->id,
                'campaign_id' => $campaign->id,
            ]);
            return 'ok';
        }

        // 💾 Salvar conversão
        $conversion = AdsConversion::create([
            'user_id'               => $campaign->user_id,
            'campaign_id'           => $campaign->id,
            'pageview_id'           => $pageview->id,
            'gclid'                 => $gclid,
            'gbraid'                => $gbraid,
            'wbraid'                => $wbraid,
            'user_agent'            => $userAgent,
            'ip_address'            => $ipAddress,
            'conversion_name'       => $campaign->conversionGoal?->goal_code,
            'conversion_value'      => (float) $request->query('amount', 1.00),
            'currency_code'         => $request->query('cy', 'USD'),
            'conversion_event_time' => now(),
            'google_upload_status'  => AdsConversion::STATUS_PENDING,
        ]);

        $log->info('Conversão criada', [
            'conversion_id' => $conversion->id
        ]);

        return 'ok';
    }

    protected function resolvePageviewIdFromToken(string $token): ?int
    {
        $value = trim($token);
        if ($value === '') {
            return null;
        }

        return app(HashidService::class)->decode($value);
    }

    protected function resolveCampaignIdFromToken(string $token): ?int
    {
        $value = trim($token);
        if ($value === '') {
            return null;
        }

        return app(HashidService::class)->decode($value);
    }

    protected function resolveUserIdFromToken(string $token): ?int
    {
        $value = trim($token);
        if ($value === '') {
            return null;
        }

        return app(HashidService::class)->decode($value);
    }
}
