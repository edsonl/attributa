<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pageview;
use App\Models\Campaign;
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
            'created_at'     => 'pageviews.created_at',
            'campaign_name'  => 'campaigns.name',
            'campaign_code'  => 'pageviews.campaign_code',
            'url'            => 'pageviews.url',
            'ip'             => 'pageviews.ip',
            'conversion'     => 'pageviews.conversion',
        ];

        $orderColumn = $sortableColumns[$sortBy] ?? 'pageviews.created_at';
        $orderDir    = $descending ? 'desc' : 'asc';

        $query = Pageview::query()
            ->leftJoin('campaigns', 'campaigns.code', '=', 'pageviews.campaign_code')
            ->select([
                'pageviews.*',
                'campaigns.name as campaign_name',
            ])
            ->orderBy($orderColumn, $orderDir);

        if ($request->filled('campaign_code')) {
            $query->where('pageviews.campaign_code', $request->campaign_code);
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
            ->select('code', 'name')
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
}
