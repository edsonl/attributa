<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\GoogleAdsAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GoogleAdsAccountController extends Controller
{
    public function index()
    {
        $accounts = GoogleAdsAccount::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($account) {
                return [
                    'id'                      => $account->id,
                    'google_ads_customer_id'  => $account->google_ads_customer_id,
                    'email'                   => $account->email,
                    'active'                  => $account->active,
                    'created_at'              => $account->created_at
                        ? $account->created_at->format('d/m/Y H:i')
                        : null,
                ];
            });

        return Inertia::render('Panel/AdsAccounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    public function toggle(GoogleAdsAccount $account)
    {
        // Segurança
        if ($account->user_id !== auth()->id()) {
            abort(403);
        }

        $account->update([
            'active' => ! $account->active,
        ]);

        $message = $account->active
            ? 'Conta de anúncios ativada com sucesso.'
            : 'Conta de anúncios desativada com sucesso.';

        return redirect()->back()->with('success', $message);
    }

    public function store(Request $request)
    {
        $oauth = session('google_oauth');

        GoogleAdsAccount::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'google_ads_customer_id' => $request->google_ads_customer_id,
            ],
            [
                'email'         => $oauth['email'],
                'refresh_token' => $oauth['refresh_token'],
                'active'        => true,
            ]
        );

        session()->forget('google_oauth');

        return redirect()
            ->route('panel.ads-accounts.index')
            ->with('success', 'Conta Google Ads conectada com sucesso.');
    }


}
