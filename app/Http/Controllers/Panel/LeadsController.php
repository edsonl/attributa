<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\AffiliatePlatform;
use App\Models\Campaign;
use App\Models\Lead;
use App\Support\CampaignDisplayNameFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LeadsController extends Controller
{
    public function index()
    {
        return Inertia::render('Panel/Leads/Index');
    }

    public function data(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'lead_statuses' => ['nullable', 'array'],
            'lead_statuses.*' => ['string', 'in:' . implode(',', Lead::ALLOWED_STATUSES)],
        ]);

        $userId = (int) auth()->id();
        $perPage = (int) $request->get('per_page', 20);
        $perPage = min(max($perPage, 5), 50);
        $sortBy = (string) $request->get('sortBy', 'created_at');
        $descending = filter_var($request->get('descending', true), FILTER_VALIDATE_BOOLEAN);

        $sortableColumns = [
            'created_at' => 'leads.created_at',
            'updated_at' => 'leads.updated_at',
            'campaign_name' => 'campaigns.name',
            'platform_name' => 'affiliate_platforms.name',
            'lead_status' => 'leads.lead_status',
            'platform_lead_id' => 'leads.platform_lead_id',
            'offer_id' => 'leads.offer_id',
            'payout_amount' => 'leads.payout_amount',
            'currency_code' => 'leads.currency_code',
            'pageview_id' => 'leads.pageview_id',
        ];

        $orderColumn = $sortableColumns[$sortBy] ?? 'leads.created_at';
        $orderDir = $descending ? 'desc' : 'asc';

        $query = Lead::query()
            ->where('leads.user_id', $userId)
            ->leftJoin('campaigns', 'campaigns.id', '=', 'leads.campaign_id')
            ->leftJoin('affiliate_platforms', 'affiliate_platforms.id', '=', 'leads.affiliate_platform_id')
            ->select([
                'leads.id',
                'leads.created_at',
                'leads.updated_at',
                'leads.campaign_id',
                'leads.pageview_id',
                'leads.affiliate_platform_id',
                'leads.platform_lead_id',
                'leads.lead_status',
                'leads.status_raw',
                'leads.offer_id',
                'leads.payout_amount',
                'leads.currency_code',
                'leads.payload_json',
                'campaigns.name as campaign_name',
                'affiliate_platforms.name as platform_name',
                DB::raw('EXISTS(SELECT 1 FROM ads_conversions ac WHERE ac.lead_id = leads.id) as has_conversion'),
            ])
            ->orderBy($orderColumn, $orderDir);

        if ($request->filled('campaign_id')) {
            $query->where('leads.campaign_id', (int) $request->query('campaign_id'));
        }

        if ($request->filled('platform_id')) {
            $query->where('leads.affiliate_platform_id', (int) $request->query('platform_id'));
        }

        $leadStatuses = $validated['lead_statuses'] ?? [];
        if (!empty($leadStatuses)) {
            $query->whereIn('leads.lead_status', $leadStatuses);
        }

        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;
        $filterTimezone = 'America/Sao_Paulo';

        if ($dateFrom && $dateTo) {
            $startUtc = Carbon::createFromFormat('Y-m-d', $dateFrom, $filterTimezone)->startOfDay()->setTimezone('UTC');
            $endUtc = Carbon::createFromFormat('Y-m-d', $dateTo, $filterTimezone)->endOfDay()->setTimezone('UTC');
            $query->whereBetween('leads.created_at', [$startUtc, $endUtc]);
        } elseif ($dateFrom) {
            $startUtc = Carbon::createFromFormat('Y-m-d', $dateFrom, $filterTimezone)->startOfDay()->setTimezone('UTC');
            $query->where('leads.created_at', '>=', $startUtc);
        } elseif ($dateTo) {
            $endUtc = Carbon::createFromFormat('Y-m-d', $dateTo, $filterTimezone)->endOfDay()->setTimezone('UTC');
            $query->where('leads.created_at', '<=', $endUtc);
        }

        $paginator = $query->paginate($perPage);
        $tz = 'America/Sao_Paulo';
        $campaignDisplayNameFormatter = app(CampaignDisplayNameFormatter::class);
        $countryCodesByCampaignId = $campaignDisplayNameFormatter->countryCodesByCampaignIds(
            $paginator->getCollection()->pluck('campaign_id')->filter()->all()
        );

        $paginator->getCollection()->transform(function ($row) use ($tz, $campaignDisplayNameFormatter, $countryCodesByCampaignId) {
            $row->created_at_formatted = $row->created_at
                ? Carbon::parse($row->created_at, 'UTC')->setTimezone($tz)->format('d/m/Y, H:i:s')
                : null;

            $row->updated_at_formatted = $row->updated_at
                ? Carbon::parse($row->updated_at, 'UTC')->setTimezone($tz)->format('d/m/Y, H:i:s')
                : null;

            $row->lead_status_label = Lead::statusLabel((string) $row->lead_status);
            $row->lead_status_color = Lead::statusColor((string) $row->lead_status);
            $row->has_conversion = (bool) $row->has_conversion;
            $campaignLabel = $campaignDisplayNameFormatter->make(
                (string) ($row->campaign_name ?? ''),
                $countryCodesByCampaignId[(int) ($row->campaign_id ?? 0)] ?? []
            );
            $row->campaign_name_base = trim((string) ($row->campaign_name ?? ''));
            $row->campaign_name = $campaignLabel['full'] !== '' ? $campaignLabel['full'] : null;
            $row->campaign_name_display = $campaignLabel['display'] !== '' ? $campaignLabel['display'] : null;
            $row->campaign_name_display_name = $campaignLabel['display_name'] !== '' ? $campaignLabel['display_name'] : null;
            $row->campaign_name_suffix = $campaignLabel['suffix'] !== '' ? $campaignLabel['suffix'] : null;

            return $row;
        });

        return response()->json($paginator);
    }

    public function campaigns()
    {
        $campaigns = Campaign::query()
            ->where('user_id', (int) auth()->id())
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        $campaignDisplayNameFormatter = app(CampaignDisplayNameFormatter::class);
        $countryCodesByCampaignId = $campaignDisplayNameFormatter->countryCodesByCampaignIds($campaigns->pluck('id')->all());

        return $campaigns->map(function (Campaign $campaign) use ($campaignDisplayNameFormatter, $countryCodesByCampaignId) {
            $campaignLabel = $campaignDisplayNameFormatter->make(
                (string) $campaign->name,
                $countryCodesByCampaignId[(int) $campaign->id] ?? []
            );

            return [
                'id' => $campaign->id,
                'name' => $campaignLabel['full'],
                'display_name' => $campaignLabel['display'],
            ];
        });
    }

    public function platforms()
    {
        return AffiliatePlatform::query()
            ->where('active', true)
            ->select('id', 'name', 'slug')
            ->orderBy('name')
            ->get();
    }

    public function destroy(Lead $lead)
    {
        if ((int) $lead->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $lead->delete();

        return response()->json([
            'deleted' => true,
            'message' => 'Lead excluído com sucesso.',
        ]);
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ids = collect($validated['ids'] ?? [])
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([
                'deleted' => 0,
                'message' => 'Nenhum lead válido para exclusão.',
            ]);
        }

        $deleted = Lead::query()
            ->where('user_id', (int) auth()->id())
            ->whereIn('id', $ids->all())
            ->delete();

        return response()->json([
            'deleted' => (int) $deleted,
            'requested' => $ids->count(),
            'message' => $deleted > 0
                ? 'Leads excluídos com sucesso.'
                : 'Nenhum lead elegível para exclusão.',
        ]);
    }
}
