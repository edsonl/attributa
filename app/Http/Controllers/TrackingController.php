<?php

namespace App\Http\Controllers;

use App\Models\Pageview;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class TrackingController extends Controller
{
    public function collect(Request $request)
    {
        // validação mínima (não bloqueante demais)
        $data = $request->validate([
            'campaign_code' => 'required|string|exists:campaigns,code',
            'url'           => 'required|string|max:500',
            'referrer'      => 'nullable|string',
            'user_agent'    => 'nullable|string',
            'timestamp'     => 'nullable|integer',
            'gclid'         => 'nullable|string|max:255',
            'gad_campaignid'=> 'nullable|string',
        ]);
        
        //Obter campanha
        $campaign = Campaign::where('code', $data['campaign_code'])->first();

        $gclid = isset($data['gclid']) ? trim((string) $data['gclid']) : null;
        if ($gclid === '') {
            $gclid = null;
        } elseif (mb_strlen($gclid) > 255) {
            $gclid = mb_substr($gclid, 0, 255);
        }

        $url = mb_substr(trim((string) $data['url']), 0, 500);

        $pageview = Pageview::create([
            'user_id'      => $campaign->user_id,
            'campaign_id'   => $campaign->id,
            'campaign_code' => $data['campaign_code'],
            'url'           => $url,
            'referrer'      => $data['referrer'] ?? null,
            'gclid'         => $gclid,
            'gad_campaignid'=> $data['gad_campaignid'] ?? null,
            'user_agent'    => $data['user_agent'] ?? $request->userAgent(),
            'ip'            => $request->ip(),
            'timestamp_ms'  => $data['timestamp'] ?? null,
            'conversion'    => 0,
        ]);

       // 🔹 Retorna o ID da visita (pageview)
        return response()->json([
            'pageview_id' => $pageview->id,
        ]);

    }

    //Retorna o script de acompanhamento
    public function script(Request $request)
    {
            // 🔹 Código da campanha vindo da URL (?c=...)
            $code = $request->query('c');

            // Código inválido
            if ($code && !preg_match('/^CMP-[A-Z]{2}-[A-Z0-9]+$/', $code)) {
                return response()->make(
                    'console.error("[Attributa] Código de campanha inválido");',
                    200,
                    [
                        'Content-Type'  => 'application/javascript',
                        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                        'Pragma'        => 'no-cache',
                        'Expires'       => '0',
                    ]
                );
            }

            // 🔹 Caminho do arquivo JS base
            $path = resource_path('views/tracking/script.js');

            if (!File::exists($path)) {
                return response()->make(
                    'console.error("[Attributa] Script base não encontrado");',
                    500,
                    ['Content-Type' => 'application/javascript']
                );
            }

            // 🔹 Lê o JS base
            $js = File::get($path);

            // 🔹 Valores dinâmicos
            $endpoint = rtrim(config('app.url'), '/') . '/api/tracking/collect';

            // 🔹 Replace seguro (JS válido)
            $replacements = [
                "'{ENDPOINT}'"       => json_encode($endpoint),
                "'{CAMPAIGN_CODE}'"  => json_encode($code),
            ];

            $js = str_replace(
                array_keys($replacements),
                array_values($replacements),
                $js
            );

            // 🔹 Retorna JS puro (stateless)
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
