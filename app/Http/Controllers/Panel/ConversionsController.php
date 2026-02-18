<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Campaign;
use App\Models\AdsConversion;
use Illuminate\Support\Carbon;

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
        $userId = (int) auth()->id();
        $perPage    = (int) $request->get('per_page', 20);
        $perPage    = min(max($perPage, 5), 100);
        $sortBy     = $request->get('sortBy', 'conversion_event_time');
        $descending = filter_var($request->get('descending', true), FILTER_VALIDATE_BOOLEAN);

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

        // filtro por campanha (ID)
        if ($request->filled('campaign_id')) {
            $query->where('ads_conversions.campaign_id', (int) $request->campaign_id);
        }

        $paginator = $query->paginate($perPage);
        $tz = 'America/Sao_Paulo';

        $paginator->getCollection()->transform(function ($row) use ($tz) {
            $statusRaw = $row->getRawOriginal('google_upload_status');
            $row->google_upload_status_slug = AdsConversion::googleUploadStatusLabel($statusRaw);
            $row->google_upload_status_label = AdsConversion::googleUploadStatusDisplayLabel($statusRaw);

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
     * API: campanhas para o filtro
     */
    public function campaigns()
    {
        return Campaign::query()
            ->where('user_id', (int) auth()->id())
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();
    }
}
