<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pageview;
use App\Models\Campaign;
use App\Models\IpLookupCache;
use Inertia\Inertia;

class ActivityController extends Controller
{
    /**
     * Renderiza a tela (Inertia)
     */
    public function pageviews()
    {
        return Inertia::render('Panel/Atividade/Pageviews');
    }

    /**
     * API: lista pageviews (JSON)
     * - paginação
     * - filtro por campanha
     * - ordenação dinâmica
     */
    public function data(Request $request)
    {
        $perPage    = $request->get('per_page', 20);
        $sortBy     = $request->get('sortBy', 'created_at');
        $descending = filter_var($request->get('descending', true), FILTER_VALIDATE_BOOLEAN);

        // Mapa de colunas permitidas para ordenação
        $sortableColumns = [
            'created_at'    => 'pageviews.created_at',
            'campaign_name' => 'campaigns.name',
            'ip_category'   => 'ip_categories.name',
            'country_code'  => 'pageviews.country_code',
            'region_name'   => 'pageviews.region_name',
            'city'          => 'pageviews.city',
            'url'           => 'pageviews.url',
            'gclid'         => 'pageviews.gclid',
            'conversion'    => 'pageviews.conversion',
            'ip'            => 'pageviews.ip',
        ];

        $orderColumn = $sortableColumns[$sortBy] ?? 'pageviews.created_at';
        $orderDir    = $descending ? 'desc' : 'asc';

        $query = Pageview::query()
            ->leftJoin('campaigns', 'campaigns.id', '=', 'pageviews.campaign_id')
            ->leftJoin('ip_categories', 'ip_categories.id', '=', 'pageviews.ip_category_id')
            ->select([
                'pageviews.*',
                'campaigns.name as campaign_name',
                'campaigns.id as campaign_internal_id',
                'ip_categories.name as ip_category_name',
                'ip_categories.color_hex as ip_category_color',
            ])
            ->orderBy($orderColumn, $orderDir);

        if ($request->filled('campaign_id')) {
            $query->where('pageviews.campaign_id', $request->campaign_id);
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
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    /**
     * API: remove um pageview específico
     */
    public function destroy(Pageview $pageview)
    {
        $pageview->delete();

        return response()->json([
            'message' => 'Pageview excluído com sucesso.',
        ]);
    }

    /**
     * API: detalhes completos de um pageview (AJAX)
     */
    public function show(Pageview $pageview)
    {
        $pageview->loadMissing([
            'ipCategory:id,name,color_hex',
            'campaign:id,name,code',
        ]);

        $urlData = $this->extractUrlData($pageview->url);

        $ipLookup = null;
        $geo = [];

        if ($pageview->ip) {
            $ipLookup = IpLookupCache::query()
                ->with('ipCategory:id,name,color_hex')
                ->where('ip', $pageview->ip)
                ->first();
        }

        $geoFields = [
            'country_code',
            'country_name',
            'region_name',
            'city',
            'latitude',
            'longitude',
            'timezone',
        ];

        foreach ($geoFields as $field) {
            $geo[$field] = $pageview->{$field} ?? $ipLookup?->{$field};
        }

        $networkInfo = [
            'isp'           => $ipLookup->isp ?? null,
            'organization'  => $ipLookup->organization ?? null,
            'flags'         => [
                'is_proxy'      => $ipLookup->is_proxy ?? null,
                'is_vpn'        => $ipLookup->is_vpn ?? null,
                'is_tor'        => $ipLookup->is_tor ?? null,
                'is_datacenter' => $ipLookup->is_datacenter ?? null,
                'is_bot'        => $ipLookup->is_bot ?? null,
            ],
            'fraud_score'   => $ipLookup->fraud_score ?? null,
            'ip_category'   => $ipLookup?->ipCategory,
            'last_checked'  => $ipLookup?->last_checked_at,
        ];

        return response()->json([
            'pageview' => $pageview,
            'url' => $urlData,
            'geo' => $geo,
            'network' => $networkInfo,
            'ip_lookup_raw' => $ipLookup,
        ]);
    }

    protected function extractUrlData(?string $url): array
    {
        if (!$url) {
            return [
                'full' => null,
                'origin' => null,
                'path' => null,
                'query_params' => [],
            ];
        }

        $parts = parse_url($url) ?: [];

        $origin = null;
        if (!empty($parts['scheme']) && !empty($parts['host'])) {
            $origin = $parts['scheme'] . '://' . $parts['host'];
            if (!empty($parts['port'])) {
                $origin .= ':' . $parts['port'];
            }
        }

        $queryParams = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $queryParams);
        }

        return [
            'full' => $url,
            'origin' => $origin,
            'path' => $parts['path'] ?? null,
            'query_params' => $queryParams,
        ];
    }
}
