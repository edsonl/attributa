<?php

namespace App\Http\Controllers;

use App\Models\AdsConversion;
use App\Models\Campaign;
use App\Models\Pageview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConversionCallbackController extends Controller
{
    public function handle(Request $request)
    {
        $log = Log::channel('affiliate_platform_callback');

        $log->info('CALLBACK RAW', $request->query());

        $campaignCode = null;
        $pageviewId   = null;

        // 🔎 Testa sub1 → sub5
        for ($i = 1; $i <= 5; $i++) {

            $sub = $request->query("subid{$i}");

            if (!$sub) {
                continue;
            }

            /**
             * Formato esperado:
             * CMP-GO-01KH6EF278-30
             *
             * Banco salva:
             * CMP-GO-01KH6EF278
             */
            if (preg_match('/^(CMP-[A-Z]{2}-[A-Z0-9]+)-(\d+)$/i', trim($sub), $matches)) {
                $campaignCode = strtoupper($matches[1]);
                $pageviewId   = (int) $matches[2];

                $log->info('SUB MATCH', [
                    'sub'           => $sub,
                    'campaign_code' => $campaignCode,
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

        // 🔎 Buscar pageview
        $pageview = Pageview::find($pageviewId);

        if (!$pageview) {
            $log->warning('Pageview não encontrada', [
                'pageview_id' => $pageviewId
            ]);
            return 'ignored';
        }

        $gclid = $pageview->gclid;

        // ✅ Marca conversão (idempotente)
        if (!$pageview->conversion) {
            $pageview->update(['conversion' => 1]);
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
}
