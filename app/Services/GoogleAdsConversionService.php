<?php

namespace App\Services;

use App\Models\AdsConversion;
use Google\Ads\GoogleAds\Lib\V20\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\V20\Services\ClickConversion;
use Google\Ads\GoogleAds\V20\Services\UploadClickConversionsRequest;
use Google\Ads\GoogleAds\Util\V20\ResourceNames;
use Google\Auth\OAuth2;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAdsConversionService
{
    /**
     * Envia uma conversão offline (GCLID) para o Google Ads.
     * Fluxo síncrono — ideal para MVP e testes iniciais.
     */
    public function send(AdsConversion $conversion): void
    {
        // 🔒 Segurança: só envia se estiver pendente
        if (AdsConversion::normalizeGoogleUploadStatus($conversion->getRawOriginal('google_upload_status')) !== AdsConversion::STATUS_PENDING) {
            Log::channel('google_ads')->info('Google Ads Upload SKIPPED (status != pending)', [
                'conversion_id' => $conversion->id,
                'status'        => $conversion->google_upload_status,
            ]);
            return;
        }

        $campaign = $conversion->campaign;
        $account  = $campaign?->googleAdsAccount;

        if (!$campaign || !$account) {
            Log::channel('google_ads')->error('Google Ads Upload FAILED (missing campaign or account)', [
                'conversion_id' => $conversion->id,
            ]);

            $conversion->update([
                'google_upload_status' => AdsConversion::STATUS_ERROR,
                'google_upload_error'  => 'Campanha ou conta Google Ads não encontrada',
            ]);
            return;
        }

        try {

            // 🔐 OAuth2 usando refresh_token
            $oAuth2 = new OAuth2([
                'clientId'              => config('services.google.client_id'),
                'clientSecret'          => config('services.google.client_secret'),
                'refreshToken'          => $account->refresh_token,
                'tokenCredentialUri'    => 'https://oauth2.googleapis.com/token',
            ]);

            // 🧱 Google Ads Client (V20)
            $googleAdsClient = (new GoogleAdsClientBuilder())
                ->withDeveloperToken(config('services.google_ads.developer_token'))
                ->withOAuth2Credential($oAuth2)
                ->build();

            // 🔍 Log de início
            Log::channel('google_ads')->info('Google Ads Conversion Upload START', [
                'conversion_id' => $conversion->id,
                'campaign_id'   => $campaign->id,
                'customer_id'   => $account->customer_id,
                'gclid'         => $conversion->gclid,
                'value'         => $conversion->conversion_value,
                'currency'      => $conversion->currency_code,
                'event_time'    => $conversion->conversion_event_time->toIso8601String(),
            ]);

            // 🎯 Monta a conversão
            $clickConversion = new ClickConversion([
                'gclid' => $conversion->gclid,
                'conversion_action' => ResourceNames::forConversionAction(
                    $account->customer_id,
                    $campaign->conversion_action_id
                ),
                'conversion_date_time' => $conversion
                    ->conversion_event_time
                    ->format('Y-m-d H:i:sP'),
                'conversion_value' => (float) $conversion->conversion_value,
                'currency_code'    => $conversion->currency_code,
            ]);

            // 🚀 Request de upload
            $request = new UploadClickConversionsRequest([
                // customer_id SEM hífen (regra do Google)
                'customer_id'     => str_replace('-', '', $account->customer_id),
                'conversions'     => [$clickConversion],
                'partial_failure' => true,
            ]);

            $service  = $googleAdsClient->getConversionUploadServiceClient();
            $response = $service->uploadClickConversions($request);

            // ⚠️ Warnings (V20 pode retornar)
            if (method_exists($response, 'getWarnings') && $response->getWarnings()) {
                Log::channel('google_ads')->warning('Google Ads Conversion Upload WARNINGS', [
                    'conversion_id' => $conversion->id,
                    'warnings'      => $response->getWarnings(),
                ]);
            }

            // ❌ Partial failure
            if ($response->hasPartialFailureError()) {
                $errorMessage = $response
                    ->getPartialFailureError()
                    ->getMessage();

                Log::channel('google_ads')->error('Google Ads Conversion Upload PARTIAL FAILURE', [
                    'conversion_id' => $conversion->id,
                    'error'         => $errorMessage,
                ]);

                throw new \Exception($errorMessage);
            }

            // ✅ Sucesso
            Log::channel('google_ads')->info('Google Ads Conversion Upload SUCCESS', [
                'conversion_id' => $conversion->id,
                'response'      => method_exists($response, 'serializeToJsonString')
                    ? $response->serializeToJsonString()
                    : json_encode($response),
            ]);

            $conversion->update([
                'google_upload_status' => AdsConversion::STATUS_SUCCESS,
                'google_uploaded_at'   => now(),
                'google_upload_error'  => null,
            ]);

        } catch (\Throwable $e) {

            Log::channel('google_ads')->error('Google Ads Conversion Upload FAILED', [
                'conversion_id' => $conversion->id,
                'campaign_id'   => $campaign->id ?? null,
                'customer_id'   => $account->customer_id ?? null,
                'gclid'         => $conversion->gclid ?? null,
                'exception'     => get_class($e),
                'message'       => $e->getMessage(),
                'file'          => $e->getFile(),
                'line'          => $e->getLine(),
            ]);

            $conversion->update([
                'google_upload_status' => AdsConversion::STATUS_ERROR,
                'google_upload_error'  => Str::limit($e->getMessage(), 1000),
            ]);
        }
    }
}
