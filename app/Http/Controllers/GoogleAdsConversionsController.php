<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\AdsConversion;

class GoogleAdsConversionsController extends Controller
{
    public function index(Request $request)
    {
        Log::channel('google_ads_https')->info('==== Google Ads HTTPS HIT ====');

        // 🔍 Request info
        Log::channel('google_ads_https')->info('Request info', [
            'ip'          => $request->ip(),
            'method'      => $request->method(),
            'url'         => $request->fullUrl(),
            'user_agent'  => $request->userAgent(),
        ]);

        // 🔐 Authorization header
        $authHeader = $request->header('Authorization');

        Log::channel('google_ads_https')->info('Authorization header', [
            'present' => (bool) $authHeader,
            'value'   => $authHeader ? substr($authHeader, 0, 30) . '...' : null,
        ]);

        if (!$authHeader || !str_starts_with($authHeader, 'Basic ')) {
            Log::channel('google_ads_https')->warning('Missing or invalid Authorization header');
            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="Google Ads Conversions"'
            ]);
        }

        // 🔓 Decode Basic Auth
        $decoded = base64_decode(substr($authHeader, 6));

        Log::channel('google_ads_https')->info('Decoded auth string', [
            'decoded' => $decoded,
        ]);

        if (!str_contains($decoded, ':')) {
            Log::channel('google_ads_https')->error('Invalid Basic Auth format');
            return response('Unauthorized', 401);
        }

        [$user, $pass] = explode(':', $decoded, 2);

        Log::channel('google_ads_https')->info('Auth credentials received', [
            'user' => $user,
            'pass_length' => strlen($pass),
        ]);

        if (
            $user !== config('services.google_ads.http_user') ||
            $pass !== config('services.google_ads.http_pass')
        ) {
            Log::channel('google_ads_https')->error('Authentication failed', [
                'expected_user' => config('services.google_ads.http_user'),
            ]);

            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="Google Ads Conversions"'
            ]);
        }

        Log::channel('google_ads_https')->info('Authentication OK');

        // 📦 Buscar conversões
        $conversions = AdsConversion::query()
            ->whereNotNull('gclid')
            ->whereNotNull('conversion_name')
            ->whereNotNull('conversion_event_time')
            ->orderBy('conversion_event_time', 'asc')
            ->limit(1000)
            ->get();

        Log::channel('google_ads_https')->info('Conversions fetched', [
            'count' => $conversions->count(),
        ]);

        if ($conversions->isEmpty()) {
            Log::channel('google_ads_https')->info('No conversions found');
            return response('', 204);
        }

        $ids = $conversions->pluck('id')->toArray();

        Log::channel('google_ads_https')->info('Conversion IDs', [
            'ids' => $ids,
        ]);

        // 📄 Gerar CSV
        $output = fopen('php://temp', 'r+');

        fputcsv($output, [
            'Google Click ID',
            'Conversion Name',
            'Conversion Time',
            'Conversion Value',
            'Conversion Currency',
            'Order ID',
        ]);

        foreach ($conversions as $c) {
            fputcsv($output, [
                $c->gclid,
                $c->conversion_name,
                $c->conversion_event_time->format('Y-m-d H:i:s'),
                number_format((float) $c->conversion_value, 2, '.', ''),
                $c->currency_code,
                'PV-' . $c->pageview_id,
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        Log::channel('google_ads_https')->info('CSV generated', [
            'bytes' => strlen($csv),
        ]);

        // ✅ Marcar como exported
        AdsConversion::whereIn('id', $ids)->update([
            'google_upload_status' => 'exported',
            'google_uploaded_at'   => now(),
        ]);

        Log::channel('google_ads_https')->info('Conversions marked as exported');

        Log::channel('google_ads_https')->info('==== END REQUEST ====');

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="google_ads_conversions.csv"',
        ]);
    }
}
