<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pageview;
use App\Models\Campaign;
use App\Models\IpLookupCache;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

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
            //'url'           => 'pageviews.url',
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
                'pageviews.id',
                'pageviews.created_at',
                'pageviews.ip',
                //'pageviews.created_at',
                'pageviews.country_code',
                'pageviews.region_name',
                'pageviews.city',
                'pageviews.conversion',
                DB::raw("CASE WHEN pageviews.gclid IS NOT NULL AND pageviews.gclid <> '' THEN 1 ELSE 0 END as has_gclid"),
                'campaigns.name as campaign_name',
                'ip_categories.name as ip_category_name',
                'ip_categories.color_hex as ip_category_color',
                'ip_categories.description as ip_category_description',
            ])
            ->orderBy($orderColumn, $orderDir);

        if ($request->filled('campaign_id')) {
            $query->where('pageviews.campaign_id', $request->campaign_id);
        }

        $paginator = $query->paginate($perPage);
        $tz = 'America/Sao_Paulo';

        $paginator->getCollection()->transform(function ($row) use ($tz) {
            $row->created_at_formatted = $row->created_at
                ? Carbon::parse($row->created_at, 'UTC')->setTimezone($tz)->format('d/m/Y, H:i:s')
                : null;

            unset($row->created_at);

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
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    /**
     * API: remove um pageview específico
     */
    public function destroy(Pageview $pageview)
    {
        if ((int) $pageview->conversion === 1) {
            return response()->json([
                'message' => 'Pageview convertido não pode ser excluído.',
                'deleted' => false,
            ]);
        }

        $pageview->delete();

        return response()->json([
            'message' => 'Pageview excluído com sucesso.',
            'deleted' => true,
        ]);
    }

    /**
     * API: remove múltiplos pageviews
     */
    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer', 'exists:pageviews,id'],
        ]);

        $query = Pageview::whereIn('id', $data['ids']);
        $totalSelected = count($data['ids']);
        $convertedCount = (clone $query)->where('conversion', 1)->count();
        $deleted = (clone $query)->where('conversion', 0)->delete();

        return response()->json([
            'message' => $convertedCount > 0
                ? 'Pageviews não convertidos excluídos. Os convertidos foram ignorados.'
                : 'Pageviews excluídos com sucesso.',
            'deleted' => $deleted,
            'ignored_converted' => $convertedCount,
            'selected' => $totalSelected,
        ]);
    }

    /**
     * API: detalhes completos de um pageview (AJAX)
     */
    public function show(Pageview $pageview)
    {
        $pageview->loadMissing([
            'ipCategory:id,name,color_hex,description',
            'campaign:id,name,code',
        ]);

        $tz = 'America/Sao_Paulo';
        $pageview->created_at_formatted = optional($pageview->created_at)
            ? Carbon::parse($pageview->created_at, 'UTC')->setTimezone($tz)->format('d/m/Y, H:i:s')
            : null;

        $urlData = $this->extractUrlData($pageview->url);

        $ipLookup = null;
        $geo = [];

        if ($pageview->ip) {
            $ipLookup = IpLookupCache::query()
                ->with('ipCategory:id,name,color_hex,description')
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
            'last_checked_formatted' => optional($ipLookup?->last_checked_at)
                ? Carbon::parse($ipLookup?->last_checked_at, 'UTC')->setTimezone($tz)->format('d/m/Y, H:i:s')
                : null,
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
