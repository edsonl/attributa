<?php

namespace App\Http\Controllers;

use App\Models\ConversionGoal;
use App\Models\ConversionGoalLog;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use App\Models\AdsConversion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAdsConversionsController extends Controller
{
    public function goalExport(Request $request, string $userSlugId, string $goalCode)
    {
        $normalizedGoalCode = trim($goalCode);

        // Resolve a meta diretamente pelos segmentos da URL de integração.
        $goal = ConversionGoal::query()
            ->with('timezone:id,identifier')
            ->where('user_slug_id', $userSlugId)
            ->where('goal_code', $normalizedGoalCode)
            ->first();

        if (!$goal) {

            $hasUserSlugParam = trim($userSlugId) !== '';
            $hasGoalCodeParam = trim($goalCode) !== '';
            $userSlugLooksValid = (bool) preg_match('/^[A-Za-z0-9]+$/', $userSlugId);
            $goalCodeLooksValid = (bool) preg_match('/^[A-Za-z0-9_-]{1,30}$/', $goalCode);

            $slugExists = ConversionGoal::query()
                ->where('user_slug_id', $userSlugId)
                ->exists();

            if ($hasUserSlugParam && $hasGoalCodeParam && $userSlugLooksValid && $goalCodeLooksValid && $slugExists) {
                $goalForUserLog = ConversionGoal::query()
                    ->where('user_slug_id', $userSlugId)
                    ->orderByDesc('id')
                    ->first();

                if ($goalForUserLog) {
                    $this->writeGoalLog(
                        $goalForUserLog,
                        'Verifique a URL e o nome da meta de conversão.',
                        'warning'
                    );
                }
            }

            return response('Not Found', 404);
        }

        $this->writeGoalLog($goal, 'Requisição CSV do Google Ads recebida.', 'info');
        $this->writeTechnicalLog($request, $goal, 'request_received');

        $expectedUser = 'googleads-' . $goal->user_slug_id;
        $expectedPass = (string) $goal->googleads_password;

        // Basic Auth por meta (usuário/senha específicos da integração dessa meta).
        $authResult = $this->authenticateBasic($request, $expectedUser, $expectedPass, $goal);
        if ($authResult !== 'success') {
            if ($authResult === 'missing_header') {
                $this->writeGoalLog($goal, 'Teste de conexão do Google Ads concluído com sucesso.', 'success');
                $this->writeTechnicalLog($request, $goal, 'connection_test_ok', 'info', [
                    'auth_result' => $authResult,
                ]);
            } else {
                $this->writeGoalLog($goal, 'Autenticação inválida na requisição CSV.', 'error');
                $this->writeTechnicalLog($request, $goal, 'authentication_failed', 'warning', [
                    'auth_result' => $authResult,
                ]);
            }

            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="Google Ads Conversions"'
            ]);
        }

        $this->writeGoalLog($goal, 'Autenticação da requisição CSV realizada com sucesso.', 'success');
        $this->writeTechnicalLog($request, $goal, 'authentication_success');

        return $this->exportConversionsByGoal($goal, $userSlugId);
    }

    protected function authenticateBasic(Request $request, string $expectedUser, string $expectedPass, ?ConversionGoal $goal = null): string
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Basic ')) {
            return 'missing_header';
        }

        $decoded = base64_decode(substr($authHeader, 6));
        if (!str_contains((string) $decoded, ':')) {
            if ($goal) {
                $this->writeGoalLog($goal, 'Formato de autenticação inválido na requisição CSV.', 'error');
            }
            return 'invalid_format';
        }

        [$user, $pass] = explode(':', $decoded, 2);

        // Garante que a credencial usada pertence à URL/Meta solicitada.
        if ($user !== $expectedUser || $pass !== $expectedPass) {
            if ($goal) {
                $this->writeGoalLog($goal, 'Credenciais inválidas na requisição CSV.', 'error');
            }
            return 'invalid_credentials';
        }

        return 'success';
    }

    protected function exportConversionsByGoal(ConversionGoal $goal, string $userSlugId)
    {
        $timezoneIdentifier = $goal->timezone?->identifier ?: 'UTC';

        // Busca conversões vinculadas exatamente ao goal da URL e ao usuário dono da meta.
        $conversions = AdsConversion::query()
            ->join('campaigns', 'campaigns.id', '=', 'ads_conversions.campaign_id')
            ->join('conversion_goals', 'conversion_goals.id', '=', 'campaigns.conversion_goal_id')
            ->where('ads_conversions.conversion_name', $goal->goal_code)
            ->where('ads_conversions.user_id', $goal->user_id)
            ->where('conversion_goals.user_id', $goal->user_id)
            ->where('conversion_goals.id', $goal->id)
            ->where('conversion_goals.user_slug_id', $userSlugId)
            ->where('conversion_goals.goal_code', $goal->goal_code)
            ->whereIn('ads_conversions.google_upload_status', [
                AdsConversion::STATUS_PENDING,
                AdsConversion::STATUS_PROCESSING,
                AdsConversion::STATUS_PROCESSING_EXPORT,
            ])
            ->where(function ($query) {
                $query->where('ads_conversions.is_manual', false)
                    ->orWhereNull('ads_conversions.is_manual');
            })
            ->whereNotNull('ads_conversions.gclid')
            ->where('ads_conversions.gclid', '<>', '')
            ->whereNotNull('ads_conversions.conversion_name')
            ->whereNotNull('ads_conversions.conversion_event_time')
            ->orderBy('ads_conversions.conversion_event_time', 'asc')
            ->limit(1000)
            ->select('ads_conversions.*')
            ->get();

        $this->writeGoalLog(
            $goal,
            'Conversões encontradas: ' . $conversions->count() . ' registro(s).',
            'info'
        );

        return $this->buildCsvAndMark($conversions, $timezoneIdentifier, $goal);
    }

    protected function buildCsvAndMark($conversions, string $timezoneIdentifier, ?ConversionGoal $goal = null)
    {
        $output = fopen('php://temp', 'r+');

        // Cabeçalho sempre presente, mesmo sem linhas de conversão.
        fputcsv($output, [
            'Google Click ID',
            'GBRAID',
            'WBRAID',
            'Conversion Name',
            'Conversion Time',
            'Conversion Value',
            'Currency Code',
            'Order ID',
            'User Agent',
            'IP Address',
        ]);

        foreach ($conversions as $c) {
            // A data é salva em UTC no banco e aqui é convertida para coincidir
            // com o fuso da conta do usuário no Google Ads.
            $eventTime = Carbon::parse($c->conversion_event_time, 'UTC')
                ->setTimezone($timezoneIdentifier)
                ->format('Y-m-d H:i:sP');

            fputcsv($output, [
                $c->gclid,
                $c->gbraid,
                $c->wbraid,
                $c->conversion_name,
                $eventTime,
                number_format((float) $c->conversion_value, 2, '.', ''),
                $c->currency_code,
                $c->pageview_id ? ('PV-' . $c->pageview_id) : ('CV-' . $c->id),
                $c->user_agent,
                $c->ip_address,
            ]);
        }

        if ($goal && (bool) $goal->csv_fake_line_enabled && $conversions->isEmpty()) {
            $fakeEventTime = now('UTC')
                ->setTimezone($timezoneIdentifier)
                ->format('Y-m-d H:i:sP');

            fputcsv($output, [
                'TEST-GCLID-' . Str::upper(Str::random(10)),
                '',
                '',
                $goal->goal_code ?: 'TEST_CONVERSION',
                $fakeEventTime,
                '1.00',
                'USD',
                'TEST-' . now()->format('YmdHis'),
                'Mozilla/5.0 (Integration Test)',
                '127.0.0.1',
            ]);

            $this->writeGoalLog(
                $goal,
                'Linha fake adicionada ao CSV para suporte à integração/mapeamento.',
                'info'
            );
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $ids = $conversions->pluck('id')->toArray();

        if ($goal) {
            $this->writeGoalLog(
                $goal,
                'CSV gerado (' . strlen((string) $csv) . ' bytes, timezone ' . $timezoneIdentifier . ').',
                'success'
            );
        }

        if (!empty($ids)) {
            // Após exportar o lote, marca os registros para evitar reexportação imediata.
            $updated = AdsConversion::query()
                ->whereIn('id', $ids)
                ->where('google_upload_status', AdsConversion::STATUS_PENDING)
                ->update([
                'google_upload_status' => AdsConversion::STATUS_PROCESSING_EXPORT,
                'google_uploaded_at'   => now(),
            ]);

            if ($goal) {
                $this->writeGoalLog(
                    $goal,
                    'Conversões marcadas como processing_export: ' . $updated . ' item(ns).',
                    'success'
                );
            }
        } elseif ($goal) {
            $this->writeGoalLog($goal, 'Nenhuma conversão disponível para marcação de processamento.', 'info');
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="google_ads_conversions.csv"',
        ]);
    }

    protected function writeGoalLog(ConversionGoal $goal, string $message, string $status = 'info'): void
    {
        $allowedStatuses = ['success', 'warning', 'error', 'info'];
        $normalizedStatus = in_array($status, $allowedStatuses, true) ? $status : 'info';

        ConversionGoalLog::query()->create([
            'goal_id' => $goal->id,
            'message' => mb_substr($message, 0, 255),
            'status' => $normalizedStatus,
        ]);

        Log::channel('google_ads_https')->info('Google Ads goal log', [
            'goal_id' => $goal->id,
            'user_slug_id' => $goal->user_slug_id,
            'goal_code' => $goal->goal_code,
            'message' => mb_substr($message, 0, 255),
            'status' => $normalizedStatus,
        ]);
    }

    protected function writeTechnicalLog(
        Request $request,
        ?ConversionGoal $goal,
        string $event,
        string $level = 'info',
        array $extra = []
    ): void {
        $authHeader = (string) $request->header('Authorization', '');
        $authorization = [
            'present' => $authHeader !== '',
            'scheme' => str_starts_with($authHeader, 'Basic ') ? 'Basic' : ($authHeader === '' ? null : 'other'),
        ];

        $headers = collect($request->headers->all())
            ->map(function ($values, $key) {
                $sensitive = ['authorization', 'cookie', 'x-api-key', 'x-auth-token'];
                if (in_array(strtolower((string) $key), $sensitive, true)) {
                    return ['[REDACTED]'];
                }

                return $values;
            })
            ->toArray();

        $context = array_merge([
            'event' => $event,
            'goal_id' => $goal?->id,
            'user_slug_id' => $goal?->user_slug_id,
            'goal_code' => $goal?->goal_code,
            'method' => $request->getMethod(),
            'path' => $request->path(),
            'full_url' => $request->fullUrl(),
            'query' => $request->query(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'authorization' => $authorization,
            'headers' => $headers,
        ], $extra);

        Log::channel('google_ads_https')->log($level, 'Google Ads HTTPS callback', $context);
    }
}
