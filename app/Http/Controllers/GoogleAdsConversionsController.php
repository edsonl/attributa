<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Models\AdsConversion;

class GoogleAdsConversionsController extends Controller
{
    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Autenticação simples por token
        |--------------------------------------------------------------------------
        */
        //$expectedToken = config('services.google_ads.http_token');
        //$authHeader   = $request->header('Authorization');
        /*
        if (!$authHeader || $authHeader !== 'Bearer ' . $expectedToken) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 401);
        }*/

        /*
        |--------------------------------------------------------------------------
        | Filtros opcionais por data
        |--------------------------------------------------------------------------
        | Exemplo:
        | /google-ads/conversions?from=2026-02-01&to=2026-02-10
        */
        $from = $request->query('from');
        $to   = $request->query('to');

        /*
        |--------------------------------------------------------------------------
        | Busca das conversões
        |--------------------------------------------------------------------------
        */
        $query = AdsConversion::query()
            ->whereNotNull('conversion_name')
            ->whereNotNull('conversion_event_time')
            ->whereNotNull('gclid');

        if ($from) {
            $query->where('conversion_event_time', '>=', $from);
        }

        if ($to) {
            $query->where('conversion_event_time', '<=', $to);
        }

        $conversions = $query
            ->orderBy('conversion_event_time', 'asc')
            ->limit(1000)
            ->get();

        // Se não houver dados, retorna vazio (com 200)
        if ($conversions->isEmpty()) {
            return response()->json([]);
        }

        /*
        |--------------------------------------------------------------------------
        | Coleta dos IDs para controle de status
        |--------------------------------------------------------------------------
        */
        $ids = $conversions->pluck('id')->toArray();

        /*
        |--------------------------------------------------------------------------
        | Payload no formato esperado pelo Google Ads
        |--------------------------------------------------------------------------
        */
        $payload = $conversions->map(function ($conversion) {
            return [
                'gclid'            => $conversion->gclid,
                'conversion_name'  => $conversion->conversion_name,
                'conversion_time'  => $conversion->conversion_event_time->format('Y-m-d H:i:s'),
                'conversion_value' => (float) $conversion->conversion_value,
                'currency'         => $conversion->currency_code,
                'order_id'         => 'PV-' . $conversion->pageview_id,
            ];
        });

        /*
        |--------------------------------------------------------------------------
        | Marca como exported (controle lógico)
        |--------------------------------------------------------------------------
        */
        AdsConversion::whereIn('id', $ids)->update([
            'google_upload_status' => 'exported',
            'google_uploaded_at'   => now(),
        ]);

        return Response::json($payload, 200, [
            'Content-Type' => 'application/json',
        ]);
    }
}
