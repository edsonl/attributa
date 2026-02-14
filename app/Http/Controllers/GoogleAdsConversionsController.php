<?php

namespace App\Http\Controllers;

use App\Models\ConversionGoal;
use App\Models\ConversionGoalLog;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use App\Models\AdsConversion;

class GoogleAdsConversionsController extends Controller
{
    public function goalExport(Request $request, string $userSlugId, string $goalCode)
    {
        // Resolve a meta diretamente pelos segmentos da URL de integração.
        $goal = ConversionGoal::query()
            ->with('timezone:id,identifier')
            ->where('user_slug_id', $userSlugId)
            ->where('goal_code', strtoupper(trim($goalCode)))
            ->first();

        if (!$goal) {
            return response('Not Found', 404);
        }

        $this->writeGoalLog($goal, 'Requisição CSV do Google Ads recebida.');

        $expectedUser = 'googleads-' . $goal->user_slug_id;
        $expectedPass = (string) $goal->googleads_password;

        // Basic Auth por meta (usuário/senha específicos da integração dessa meta).
        if (!$this->authenticateBasic($request, $expectedUser, $expectedPass, $goal)) {
            $this->writeGoalLog($goal, 'Falha na autenticação da requisição CSV.');
            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="Google Ads Conversions"'
            ]);
        }

        $this->writeGoalLog($goal, 'Autenticação da requisição CSV realizada com sucesso.');

        return $this->exportConversionsByGoal($goal, $userSlugId);
    }

    protected function authenticateBasic(Request $request, string $expectedUser, string $expectedPass, ?ConversionGoal $goal = null): bool
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Basic ')) {
            if ($goal) {
                $this->writeGoalLog($goal, 'Header Authorization ausente ou inválido.');
            }
            return false;
        }

        $decoded = base64_decode(substr($authHeader, 6));
        if (!str_contains((string) $decoded, ':')) {
            if ($goal) {
                $this->writeGoalLog($goal, 'Formato de Basic Auth inválido.');
            }
            return false;
        }

        [$user, $pass] = explode(':', $decoded, 2);

        // Garante que a credencial usada pertence à URL/Meta solicitada.
        if ($user !== $expectedUser || $pass !== $expectedPass) {
            if ($goal) {
                $this->writeGoalLog($goal, 'Credenciais inválidas para requisição CSV.');
            }
            return false;
        }

        return true;
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
            ->where('google_upload_status', 'pending')
            ->whereNotNull('ads_conversions.gclid')
            ->whereNotNull('ads_conversions.conversion_name')
            ->whereNotNull('ads_conversions.conversion_event_time')
            ->orderBy('ads_conversions.conversion_event_time', 'asc')
            ->limit(1000)
            ->select('ads_conversions.*')
            ->get();

        $this->writeGoalLog(
            $goal,
            'Conversões encontradas: ' . $conversions->count() . ' registro(s).'
        );

        return $this->buildCsvAndMark($conversions, $timezoneIdentifier, $goal);
    }

    protected function buildCsvAndMark($conversions, string $timezoneIdentifier, ?ConversionGoal $goal = null)
    {
        $output = fopen('php://temp', 'r+');

        // Cabeçalho sempre presente, mesmo sem linhas de conversão.
        fputcsv($output, [
            'Google Click ID',
            'Conversion Name',
            'Conversion Time',
            'Conversion Value',
            'Conversion Currency',
            'Order ID',
        ]);

        foreach ($conversions as $c) {
            // A data é salva em UTC no banco e aqui é convertida para coincidir
            // com o fuso da conta do usuário no Google Ads.
            $eventTime = Carbon::parse($c->conversion_event_time, 'UTC')
                ->setTimezone($timezoneIdentifier)
                ->format('Y-m-d H:i:sP');

            fputcsv($output, [
                $c->gclid,
                $c->conversion_name,
                $eventTime,
                number_format((float) $c->conversion_value, 2, '.', ''),
                $c->currency_code,
                'PV-' . $c->pageview_id,
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $ids = $conversions->pluck('id')->toArray();

        if ($goal) {
            $this->writeGoalLog(
                $goal,
                'CSV gerado (' . strlen((string) $csv) . ' bytes, timezone ' . $timezoneIdentifier . ').'
            );
        }

        if (!empty($ids)) {
            // Após exportar o lote, marca os registros para evitar reexportação imediata.
            AdsConversion::whereIn('id', $ids)->update([
                'google_upload_status' => 'prossecing',
                'google_uploaded_at'   => now(),
            ]);

            if ($goal) {
                $this->writeGoalLog(
                    $goal,
                    'Conversões marcadas como prossecing: ' . count($ids) . ' item(ns).'
                );
            }
        } elseif ($goal) {
            $this->writeGoalLog($goal, 'Nenhuma conversão para marcar como prossecing.');
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="google_ads_conversions.csv"',
        ]);
    }

    protected function writeGoalLog(ConversionGoal $goal, string $message): void
    {
        ConversionGoalLog::query()->create([
            'goal_id' => $goal->id,
            'message' => mb_substr($message, 0, 255),
        ]);
    }
}
