<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Campaign;
use App\Models\AdsConversion;

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
        $perPage    = (int) $request->get('per_page', 20);
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
            'gclid'                 => 'ads_conversions.gclid',
            'pageview_url'          => 'pageviews.url',
            'pageview_ip'           => 'pageviews.ip',
            'created_at'            => 'ads_conversions.created_at',
        ];

        $orderColumn = $sortableColumns[$sortBy] ?? 'ads_conversions.conversion_event_time';
        $orderDir    = $descending ? 'desc' : 'asc';

        $query = AdsConversion::query()
            ->leftJoin('campaigns', 'campaigns.id', '=', 'ads_conversions.campaign_id')
            ->leftJoin('pageviews', 'pageviews.id', '=', 'ads_conversions.pageview_id')
            ->select([
                'ads_conversions.*',
                'campaigns.name as campaign_name',
                'campaigns.code as campaign_code',
                'pageviews.url as pageview_url',
                'pageviews.ip as pageview_ip',
            ])
            ->orderBy($orderColumn, $orderDir);

        // filtro por campanha (ID)
        if ($request->filled('campaign_id')) {
            $query->where('ads_conversions.campaign_id', (int) $request->campaign_id);
        }

        return response()->json(
            $query->paginate($perPage)
        );
    }

    /**
     * API: campanhas para o filtro
     */
    public function campaigns()
    {
        return Campaign::query()
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();
    }
}
