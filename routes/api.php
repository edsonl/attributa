<?php

use App\Http\Controllers\TrackingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

Route::post('/tracking/collect', [TrackingController::class, 'collect'])
    ->middleware('throttle:tracking')
->name('tracking.collect');

Route::get('/tracking/script.js', function (Request $request) {

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
});

