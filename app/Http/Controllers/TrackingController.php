<?php

namespace App\Http\Controllers;

use App\Models\Pageview;
use App\Models\Campaign;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function collect(Request $request)
    {
        // validação mínima (não bloqueante demais)
        $data = $request->validate([
            'campaign_code' => 'required|string|exists:campaigns,code',
            'url'           => 'required|string',
            'referrer'      => 'nullable|string',
            'user_agent'    => 'nullable|string',
            'timestamp'     => 'nullable|integer',
        ]);

        Pageview::create([
            'campaign_code' => $data['campaign_code'],
            'url'           => $data['url'],
            'referrer'      => $data['referrer'] ?? null,
            'user_agent'    => $data['user_agent'] ?? $request->userAgent(),
            'ip'            => $request->ip(),
            'timestamp_ms'  => $data['timestamp'] ?? null,
        ]);

        // tracking nunca deve retornar conteúdo
        return response()->noContent(); // 204
    }
}
