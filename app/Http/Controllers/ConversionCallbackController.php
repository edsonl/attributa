<?php

namespace App\Http\Controllers;

use App\Models\AdsConversion;
use App\Models\Campaign;
use App\Models\Pageview;
use App\Services\GoogleAdsConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConversionCallbackController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('ADS CALLBACK RAW', $request->query());

        $campaignCode = null;
        $pageviewId   = null;

        // 🔎 Testa sub1 → sub5 (ordem importa)
        for ($i = 1; $i <= 5; $i++) {

            $sub = $request->query("sub{$i}");

            if (!$sub) {
                continue;
            }

            // Formato: CMP-GO-01KGW3QK31-56
            if (preg_match('/^(CMP-.+)-([0-9]+)$/i', $sub, $matches)) {
                $campaignCode = $matches[1];
                $pageviewId   = (int) $matches[2];
                break;
            }
        }

        if (!$campaignCode || !$pageviewId) {
            Log::warning('ADS CALLBACK: código CMP inválido');
            return 'ignored';
        }

        // 🔎 Buscar pageview
        $pageview = Pageview::find($pageviewId);

        if (!$pageview) {
            Log::warning('ADS CALLBACK: pageview não encontrada', [
                'pageview_id' => $pageviewId
            ]);
            return 'ignored';
        }

        // 🔑 GCLID vem da pageview
        $gclid = $pageview->gclid;

        // ✅ Marca conversão (idempotente)
        if (!$pageview->conversion) {
            $pageview->update(['conversion' => 1]);
        }

        // 🔎 Buscar campanha pelo código completo
        $campaign = Campaign::where('code', $campaignCode)->first();

        if (!$campaign) {return "";}

        if (!$gclid) {
            Log::warning('ADS CALLBACK: conversão sem gclid', [
                'pageview_id' => $pageview->id,
                'campaign_id' => $campaign->id,
            ]);
        }

        $existingConversion = AdsConversion::where('pageview_id', $pageview->id)
            ->where('campaign_id', $campaign->id)
            ->first();

        if ($existingConversion) {
            Log::info('ADS CALLBACK: conversão já registrada', [
                'pageview_id' => $pageview->id,
                'campaign_id' => $campaign->id,
            ]);
            return 'ok';
        }

        // 💾 Salvar conversão
        $conversion =  AdsConversion::create([
            'campaign_id'           => $campaign->id,
            'pageview_id'           => $pageview->id,
            'gclid'                 => $gclid,
            'conversion_name'       => $campaign->pixel_code, // mapeia com conversion_action
            'conversion_value'      => (float) $request->query('amount', 1.00),
            'currency_code'         => $request->query('cy', 'USD'),
            'conversion_event_time' => now(), // venda confirmada
            'google_upload_status'  => 'pending',
        ]);

        // 🔥 ENVIO DIRETO
        //app(GoogleAdsConversionService::class)->send($conversion);

        return 'ok';
    }
}
