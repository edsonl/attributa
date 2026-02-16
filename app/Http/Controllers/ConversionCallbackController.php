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

            /**
             * Formato esperado:
             * CMP-GO-01KH6EF278-0xAbCd12 (novo hash)
             *
             * Banco salva:
             * CMP-GO-01KH6EF278
             */
            if (preg_match('/^(CMP-[A-Z]{2}-[A-Z0-9]+)-([A-Za-z0-9]+)$/i', trim((string) $sub), $matches)) {
                $campaignCode = strtoupper($matches[1]);
                $pageviewToken = (string) $matches[2];
                $pageviewId = $this->resolvePageviewIdFromToken($pageviewToken);

                $log->info('SUB MATCH', [
                    'source_field'   => $subField,
                    'sub'           => $sub,
                    'campaign_code' => $campaignCode,
                    'pageview_token'=> $pageviewToken,
                    'pageview_id'   => $pageviewId,
                ]);

                break;
            }
        }

        if (!$campaignCode || !$pageviewId) {
            $log->warning('Código CMP inválido', [
                'query' => $request->query()
            ]);
            return 'ignored';
        }

        // 🔎 Buscar campanha
        $campaign = Campaign::with('conversionGoal')
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

        $gclid = $pageview->gclid;

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
            'conversion_name'       => $campaign->conversionGoal?->goal_code,
            'conversion_value'      => (float) $request->query('amount', 1.00),
            'currency_code'         => $request->query('cy', 'USD'),
            'conversion_event_time' => now(),
            'google_upload_status'  => 'pending',
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
}
