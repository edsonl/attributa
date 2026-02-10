<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\GoogleAdsAccount;
use Google\Client as GoogleClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(route('panel.google.callback'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setScopes([
            'https://www.googleapis.com/auth/adwords',
            'email',
        ]);

        //dd(route('panel.google.callback'));

        return redirect()->away($client->createAuthUrl());
    }

    public function callback(Request $request)
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(route('panel.google.callback'));

        $token = $client->fetchAccessTokenWithAuthCode($request->code);

        if (!isset($token['refresh_token'])) {
            return redirect()
                ->route('panel.ads-accounts.index')
                ->withErrors('Não foi possível obter o refresh token.');
        }

        $client->setAccessToken($token);

        // 🔹 Descobrir contas Google Ads disponíveis
        $response = Http::withToken($token['access_token'])
            ->get('https://googleads.googleapis.com/v16/customers:listAccessibleCustomers');

        $customers = collect($response->json('resourceNames'))
            ->map(fn ($c) => str_replace('customers/', '', $c));

        // Guardar temporariamente na sessão
        session([
            'google_oauth' => [
                'refresh_token' => $token['refresh_token'],
                'email'         => $client->verifyIdToken()['email'] ?? null,
                'customers'     => $customers,
            ]
        ]);

        return Inertia::render('Panel/AdsAccounts/SelectCustomer', [
            'customers' => $customers,
        ]);
    }
}
