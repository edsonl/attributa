<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\StoreManualConversionRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Campaign;
use App\Models\AdsConversion;
use App\Models\Timezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ConversionsController extends Controller
{
    /**
     * Renderiza a tela (Inertia)
     */
    public function index()
    {
        return Inertia::render('Panel/Conversions/Index');
    }

    /**
     * API: lista conversões (JSON)
     * - paginação
     * - filtro por campaign_id
     * - ordenação dinâmica (todas as colunas exibidas)
     */
    public function data(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $userId = (int) auth()->id();
        $perPage    = (int) $request->get('per_page', 20);
        $perPage    = min(max($perPage, 5), 50);
        $sortBy     = $request->get('sortBy', 'conversion_event_time');
        $descending = filter_var($request->get('descending', true), FILTER_VALIDATE_BOOLEAN);
        $includeManual = filter_var($request->query('include_manual', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $includeAutomatic = filter_var($request->query('include_automatic', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $includeManual = $includeManual ?? true;
        $includeAutomatic = $includeAutomatic ?? true;

        // Colunas permitidas para ordenação (mapa -> coluna real do banco)
        $sortableColumns = [
            'conversion_event_time' => 'ads_conversions.conversion_event_time',
            'campaign_name'         => 'campaigns.name',
            'campaign_code'         => 'campaigns.code',
            'conversion_name'       => 'ads_conversions.conversion_name',
            'conversion_value'      => 'ads_conversions.conversion_value',
            'currency_code'         => 'ads_conversions.currency_code',
            'pageview_id'           => 'pageviews.id',
            'country_code'          => 'pageviews.country_code',
            'region_name'           => 'pageviews.region_name',
            'city'                  => 'pageviews.city',
            'google_upload_status'  => 'ads_conversions.google_upload_status',
            'created_at'            => 'ads_conversions.created_at',
        ];

        $orderColumn = $sortableColumns[$sortBy] ?? 'ads_conversions.conversion_event_time';
        $orderDir    = $descending ? 'desc' : 'asc';

        $query = AdsConversion::query()
            ->where('ads_conversions.user_id', $userId)
            ->leftJoin('campaigns', 'campaigns.id', '=', 'ads_conversions.campaign_id')
            ->leftJoin('pageviews', 'pageviews.id', '=', 'ads_conversions.pageview_id')
            ->select([
                'ads_conversions.*',
                'campaigns.name as campaign_name',
                'campaigns.code as campaign_code',
                'pageviews.id as pageview_id',
                'pageviews.country_code',
                'pageviews.region_name',
                'pageviews.city',
            ])
            ->orderBy($orderColumn, $orderDir);

        if ($includeManual && !$includeAutomatic) {
            $query->where('ads_conversions.is_manual', true);
        } elseif (!$includeManual && $includeAutomatic) {
            $query->where(function ($sub) {
                $sub->where('ads_conversions.is_manual', false)
                    ->orWhereNull('ads_conversions.is_manual');
            });
        }

        // filtro por campanha (ID)
        if ($request->filled('campaign_id')) {
            $query->where('ads_conversions.campaign_id', (int) $request->campaign_id);
        }

        // Filtro de período no timezone da operação (America/Sao_Paulo), convertido para UTC na consulta.
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;
        $filterTimezone = 'America/Sao_Paulo';

        if ($dateFrom && $dateTo) {
            $startUtc = Carbon::createFromFormat('Y-m-d', $dateFrom, $filterTimezone)->startOfDay()->setTimezone('UTC');
            $endUtc = Carbon::createFromFormat('Y-m-d', $dateTo, $filterTimezone)->endOfDay()->setTimezone('UTC');
            $query->whereBetween('ads_conversions.conversion_event_time', [$startUtc, $endUtc]);
        } elseif ($dateFrom) {
            $startUtc = Carbon::createFromFormat('Y-m-d', $dateFrom, $filterTimezone)->startOfDay()->setTimezone('UTC');
            $query->where('ads_conversions.conversion_event_time', '>=', $startUtc);
        } elseif ($dateTo) {
            $endUtc = Carbon::createFromFormat('Y-m-d', $dateTo, $filterTimezone)->endOfDay()->setTimezone('UTC');
            $query->where('ads_conversions.conversion_event_time', '<=', $endUtc);
        }

        $paginator = $query->paginate($perPage);
        $tz = 'America/Sao_Paulo';

        $paginator->getCollection()->transform(function ($row) use ($tz) {
            $statusRaw = $row->getRawOriginal('google_upload_status');
            $row->google_upload_status_slug = AdsConversion::googleUploadStatusLabel($statusRaw);
            $row->google_upload_status_label = AdsConversion::googleUploadStatusDisplayLabel($statusRaw);
            $row->type = (bool) $row->is_manual ? 'manual' : 'automatic';

            $row->conversion_event_time_formatted = $row->conversion_event_time
                ? Carbon::parse($row->conversion_event_time, 'UTC')->setTimezone($tz)->format('d/m/Y, H:i:s')
                : null;

            $row->created_at_formatted = $row->created_at
                ? Carbon::parse($row->created_at, 'UTC')->setTimezone($tz)->format('d/m/Y, H:i:s')
                : null;

            return $row;
        });

        return response()->json($paginator);
    }

    /**
     * API: exclui conversão
     */
    public function destroy(AdsConversion $conversion)
    {
        $userId = (int) auth()->id();

        if ((int) $conversion->user_id !== $userId) {
            abort(404);
        }

        $isManual = (bool) $conversion->is_manual;
        $conversion->delete();

        return response()->json([
            'message' => $isManual
                ? 'Conversão manual excluída com sucesso.'
                : 'Conversão automática excluída com sucesso.',
        ]);
    }

    /**
     * API: campanhas para o filtro
     */
    public function campaigns()
    {
        return Campaign::query()
            ->with('conversionGoal.timezone:id,identifier')
            ->where('user_id', (int) auth()->id())
            ->select('id', 'name', 'code', 'conversion_goal_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Campaign $campaign) => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'code' => $campaign->code,
                'timezone_identifier' => $campaign->conversionGoal?->timezone?->identifier ?: null,
            ]);
    }

    /**
     * API: timezones ativas para seleção no cadastro manual
     */
    public function timezones()
    {
        return Timezone::query()
            ->where('active', true)
            ->orderBy('utc_offset')
            ->orderBy('identifier')
            ->get(['identifier', 'label', 'utc_offset'])
            ->map(fn (Timezone $timezone) => [
                'identifier' => $timezone->identifier,
                'label' => $timezone->label ?: $timezone->identifier,
                'utc_offset' => $timezone->utc_offset,
            ]);
    }

    /**
     * API: cria conversão manual vinculada à campanha
     */
    public function storeManual(StoreManualConversionRequest $request)
    {
        $userId = (int) auth()->id();
        $validated = $request->validated();

        $campaign = Campaign::query()
            ->with('conversionGoal:id,goal_code')
            ->where('user_id', $userId)
            ->findOrFail((int) $validated['campaign_id']);

        $eventTimezone = (string) ($validated['conversion_timezone'] ?? 'UTC');
        $eventTime = Carbon::parse((string) $validated['conversion_event_time'], $eventTimezone)
            ->setTimezone('UTC');
        $gclid = isset($validated['gclid']) ? trim((string) $validated['gclid']) : null;
        if ($gclid === '') {
            $gclid = null;
        } elseif (mb_strlen($gclid) > 150) {
            $gclid = mb_substr($gclid, 0, 150);
        }

        $conversionValue = (float) ($validated['conversion_value'] ?? 1);
        if ($conversionValue <= 0) {
            $conversionValue = 1;
        }

        $conversion = DB::transaction(function () use ($campaign, $conversionValue, $eventTime, $userId, $gclid) {
            return AdsConversion::create([
                'user_id' => $userId,
                'created_by' => $userId,
                'is_manual' => true,
                'campaign_id' => $campaign->id,
                'pageview_id' => null,
                'gclid' => $gclid,
                'conversion_name' => $campaign->conversionGoal?->goal_code ?: 'MANUAL',
                'conversion_value' => $conversionValue,
                'currency_code' => 'USD',
                'conversion_event_time' => $eventTime,
                'google_upload_status' => AdsConversion::STATUS_PENDING,
            ]);
        });

        return response()->json([
            'message' => 'Conversão manual cadastrada com sucesso.',
            'data' => [
                'id' => $conversion->id,
                'campaign_id' => $conversion->campaign_id,
                'pageview_id' => $conversion->pageview_id,
            ],
        ], 201);
    }

    /**
     * API: retorna faixa de datas disponível para exportação CSV por campanha
     */
    public function exportRange(Request $request)
    {
        $userId = (int) auth()->id();
        $validated = $request->validate([
            'campaign_id' => ['required', 'integer'],
        ]);

        $campaign = Campaign::query()
            ->with('conversionGoal.timezone:id,identifier')
            ->where('user_id', $userId)
            ->findOrFail((int) $validated['campaign_id']);

        $timezoneIdentifier = $campaign->conversionGoal?->timezone?->identifier ?: 'UTC';

        $range = AdsConversion::query()
            ->where('user_id', $userId)
            ->where('campaign_id', $campaign->id)
            ->whereNotNull('conversion_event_time')
            ->selectRaw('MIN(conversion_event_time) as min_event_time, MAX(conversion_event_time) as max_event_time')
            ->first();

        $minUtc = $range?->min_event_time ? Carbon::parse($range->min_event_time, 'UTC') : null;
        $maxUtc = $range?->max_event_time ? Carbon::parse($range->max_event_time, 'UTC') : null;

        return response()->json([
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'timezone' => $timezoneIdentifier,
            'has_rows' => $minUtc !== null && $maxUtc !== null,
            'min_datetime_local' => $minUtc?->copy()->setTimezone($timezoneIdentifier)->format('Y-m-d\TH:i'),
            'max_datetime_local' => $maxUtc?->copy()->setTimezone($timezoneIdentifier)->format('Y-m-d\TH:i'),
        ]);
    }

    /**
     * API: exporta CSV Google para campanha/intervalo sem filtrar por status de envio
     */
    public function exportCsv(Request $request)
    {
        $userId = (int) auth()->id();
        $request->merge([
            'include_manual' => filter_var(
                $request->query('include_manual', true),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ),
            'include_automatic' => filter_var(
                $request->query('include_automatic', true),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ),
        ]);

        $validated = $request->validate([
            'campaign_id' => ['required', 'integer'],
            'date_from' => ['required', 'date_format:Y-m-d\TH:i'],
            'date_to' => ['required', 'date_format:Y-m-d\TH:i'],
            'include_manual' => ['nullable', 'boolean'],
            'include_automatic' => ['nullable', 'boolean'],
        ]);

        $includeManual = filter_var($validated['include_manual'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $includeAutomatic = filter_var($validated['include_automatic'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        $includeManual = $includeManual ?? true;
        $includeAutomatic = $includeAutomatic ?? true;

        if (!$includeManual && !$includeAutomatic) {
            return response()->json([
                'message' => 'Selecione ao menos um tipo de conversão para exportar.',
            ], 422);
        }

        $campaign = Campaign::query()
            ->with('conversionGoal.timezone:id,identifier')
            ->where('user_id', $userId)
            ->findOrFail((int) $validated['campaign_id']);

        $timezoneIdentifier = $campaign->conversionGoal?->timezone?->identifier ?: 'UTC';

        $minMax = AdsConversion::query()
            ->where('user_id', $userId)
            ->where('campaign_id', $campaign->id)
            ->whereNotNull('conversion_event_time')
            ->selectRaw('MIN(conversion_event_time) as min_event_time, MAX(conversion_event_time) as max_event_time')
            ->first();

        if (!$minMax?->min_event_time || !$minMax?->max_event_time) {
            return response()->json([
                'message' => 'Não há conversões com identificadores de clique para exportar nesta campanha.',
            ], 422);
        }

        $minLocal = Carbon::parse($minMax->min_event_time, 'UTC')->setTimezone($timezoneIdentifier);
        $maxLocal = Carbon::parse($minMax->max_event_time, 'UTC')->setTimezone($timezoneIdentifier);
        $minAllowed = $minLocal->copy()->startOfMinute();
        $maxAllowed = $maxLocal->copy()->endOfMinute();

        $fromLocal = Carbon::createFromFormat('Y-m-d\TH:i', $validated['date_from'], $timezoneIdentifier)->startOfMinute();
        $toLocal = Carbon::createFromFormat('Y-m-d\TH:i', $validated['date_to'], $timezoneIdentifier)->endOfMinute();

        if ($fromLocal->greaterThan($toLocal)) {
            return response()->json([
                'message' => 'A data inicial deve ser menor ou igual à data final.',
            ], 422);
        }

        if ($fromLocal->lt($minAllowed) || $toLocal->gt($maxAllowed)) {
            return response()->json([
                'message' => 'O intervalo selecionado está fora da faixa disponível de registros da campanha.',
                'min_datetime_local' => $minAllowed->format('Y-m-d\TH:i'),
                'max_datetime_local' => $maxAllowed->format('Y-m-d\TH:i'),
            ], 422);
        }

        $fromUtc = $fromLocal->copy()->setTimezone('UTC');
        $toUtc = $toLocal->copy()->setTimezone('UTC');

        $rows = AdsConversion::query()
            ->where('user_id', $userId)
            ->where('campaign_id', $campaign->id)
            ->whereNotNull('gclid')
            ->where('gclid', '<>', '')
            ->whereNotNull('conversion_name')
            ->whereNotNull('conversion_event_time')
            ->when(!$includeManual, fn ($q) => $q->where(function ($sub) {
                $sub->where('is_manual', false)->orWhereNull('is_manual');
            }))
            ->when(!$includeAutomatic, fn ($q) => $q->where('is_manual', true))
            ->whereBetween('conversion_event_time', [$fromUtc, $toUtc])
            ->orderBy('conversion_event_time', 'asc')
            ->get([
                'id',
                'gclid',
                'gbraid',
                'wbraid',
                'conversion_name',
                'conversion_event_time',
                'conversion_value',
                'currency_code',
                'pageview_id',
            ]);

        $output = fopen('php://temp', 'r+');
        fputcsv($output, [
            'Google Click ID',
            'GBRAID',
            'WBRAID',
            'Conversion Name',
            'Conversion Time',
            'Conversion Value',
            'Conversion Currency',
            'Order ID',
        ]);

        foreach ($rows as $row) {
            $eventTime = Carbon::parse($row->conversion_event_time, 'UTC')
                ->setTimezone($timezoneIdentifier)
                ->format('Y-m-d H:i:sP');

            fputcsv($output, [
                $row->gclid,
                $row->gbraid,
                $row->wbraid,
                $row->conversion_name,
                $eventTime,
                number_format((float) $row->conversion_value, 2, '.', ''),
                $row->currency_code ?: 'USD',
                $row->pageview_id ? ('PV-' . $row->pageview_id) : ('CV-' . $row->id),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $filename = 'google_ads_conversions_campaign_' . $campaign->id . '_' . now()->format('Ymd_His') . '.csv';

        return response((string) $csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
