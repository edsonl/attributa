<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AdsConversion;
use Symfony\Component\HttpFoundation\Response;

class GoogleAdsConversionsController extends Controller
{
    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Basic Authentication (obrigatório nesse fluxo)
        |--------------------------------------------------------------------------
        */
        $user = $request->getUser();
        $pass = $request->getPassword();

        if (
            $user !== config('services.google_ads.http_user') ||
            $pass !== config('services.google_ads.http_pass')
        ) {
            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="Google Ads Conversions"'
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Buscar conversões
        |--------------------------------------------------------------------------
        */
        $conversions = AdsConversion::query()
            ->whereNotNull('gclid')
            ->whereNotNull('conversion_name')
            ->whereNotNull('conversion_event_time')
            ->orderBy('conversion_event_time', 'asc')
            ->limit(1000)
            ->get();

        if ($conversions->isEmpty()) {
            return response('', 204);
        }

        $ids = $conversions->pluck('id')->toArray();

        /*
        |--------------------------------------------------------------------------
        | Montar CSV
        |--------------------------------------------------------------------------
        */
        $lines = [];
        $lines[] = [
            'Google Click ID',
            'Conversion Name',
            'Conversion Time',
            'Conversion Value',
            'Conversion Currency',
            'Order ID',
        ];

        foreach ($conversions as $c) {
            $lines[] = [
                $c->gclid,
                $c->conversion_name,
                $c->conversion_event_time->format('Y-m-d H:i:s'),
                number_format((float) $c->conversion_value, 2, '.', ''),
                $c->currency_code,
                'PV-' . $c->pageview_id,
            ];
        }

        $output = fopen('php://temp', 'r+');
        foreach ($lines as $line) {
            fputcsv($output, $line);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        /*
        |--------------------------------------------------------------------------
        | Marcar como exported
        |--------------------------------------------------------------------------
        */
        AdsConversion::whereIn('id', $ids)->update([
            'google_upload_status' => 'exported',
            'google_uploaded_at'   => now(),
        ]);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="google_ads_conversions.csv"',
        ]);
    }
}
