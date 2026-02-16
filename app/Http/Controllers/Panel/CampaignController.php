<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\StoreCampaignRequest;
use App\Http\Requests\Panel\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Models\Channel;
use App\Models\Country;
use App\Models\AffiliatePlatform;
use App\Models\ConversionGoal;
use App\Models\GoogleAdsAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CampaignController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $userId = (int) auth()->id();
        $search = (string) $request->string('search')->trim();
        $sort = $request->input('sort', 'created_at');
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = (int) $request->input('per_page', 15);
        $perPage = min(max($perPage, 5), 100);

        $allowedSorts = ['id', 'code', 'name', 'status', 'created_at'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $campaignsQuery = Campaign::with([
            'channel',
            'conversionGoal',
        ])
            ->withCount('countries')
            ->where('user_id', $userId);

        if ($search !== '') {
            $campaignsQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $campaigns = $campaignsQuery
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        $campaignIds = collect($campaigns->items())
            ->pluck('id')
            ->filter()
            ->values()
            ->all();

        $countriesPreviewByCampaign = collect();

        if (!empty($campaignIds)) {
            $rankedCountries = DB::table('campaign_country as cc')
                ->join('countries as c', 'c.id', '=', 'cc.country_id')
                ->whereIn('cc.campaign_id', $campaignIds)
                ->selectRaw('cc.campaign_id, c.name, ROW_NUMBER() OVER (PARTITION BY cc.campaign_id ORDER BY c.name) as row_num');

            $countriesPreviewByCampaign = DB::query()
                ->fromSub($rankedCountries, 'ranked_countries')
                ->where('row_num', '<=', 2)
                ->orderBy('campaign_id')
                ->orderBy('name')
                ->get()
                ->groupBy('campaign_id')
                ->map(fn ($rows) => collect($rows)->pluck('name')->values()->all());
        }

        $campaigns->setCollection(
            $campaigns->getCollection()->map(function (Campaign $campaign) use ($countriesPreviewByCampaign) {
                $preview = $countriesPreviewByCampaign->get($campaign->id, []);
                $countriesCount = (int) ($campaign->countries_count ?? 0);

                $campaign->setAttribute('countries_preview', $preview);
                $campaign->setAttribute('countries_hidden_count', max($countriesCount - count($preview), 0));

                return $campaign;
            })
        );

        return Inertia::render('Panel/Campaigns/Index', [
            'campaigns' => $campaigns,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Panel/Campaigns/Create', [
            'channels'  => $this->channelOptions(),
            'countries' => Country::orderBy('name')->get(),
            'affiliate_platforms' => $this->affiliatePlatformOptions(),
            'googleAdsAccounts' => GoogleAdsAccount::where('user_id', auth()->id())
                ->where('active', true)
                ->orderBy('google_ads_customer_id')
                ->get()
                ->map(fn ($acc) => [
                    'id'    => $acc->id,
                    'label' => $acc->google_ads_customer_id . ($acc->email ? ' - ' . $acc->email : ''),
                ]),
            'conversionGoals' => ConversionGoal::query()
                ->where('user_id', (int) auth()->id())
                ->where('active', true)
                ->orderBy('goal_code')
                ->get(['id', 'goal_code', 'active'])
                ->map(fn ($goal) => [
                    'id' => $goal->id,
                    'label' => $goal->goal_code,
                    'active' => (bool) $goal->active,
                ]),
            'defaults' => $this->campaignDefaults(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCampaignRequest $request)
    {
        $data = $request->validated();

        $campaign = Campaign::create([
            'user_id'    => auth()->id(),
            'name'       => $data['name'],
            'product_url' => $data['product_url'],
            'conversion_goal_id' => $data['conversion_goal_id'] ?? null,
            'status'     => $data['status'],
            'channel_id' => $data['channel_id'],
            'affiliate_platform_id' => $data['affiliate_platform_id'],
            'google_ads_account_id' => $data['google_ads_account_id'],
            'commission_value' => $data['commission_value'] ?? null,
        ]);

        if (!empty($data['countries'])) {
            $campaign->countries()->sync($data['countries']);
        }

        return redirect()
            ->route('panel.campaigns.index')
            ->with('success', 'Campanha criada com sucesso.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Campaign $campaign)
    {
        return Inertia::render('Panel/Campaigns/Edit', [
            'googleAdsAccounts' => GoogleAdsAccount::where('user_id', auth()->id())
                ->where('active', true)
                ->orderBy('google_ads_customer_id')
                ->get()
                ->map(fn ($acc) => [
                    'id'    => $acc->id,
                    'label' => $acc->google_ads_customer_id . ($acc->email ? ' - ' . $acc->email : ''),
             ]),
            'campaign'  => $campaign->load('countries'),
            'channels'  => $this->channelOptions(),
            'countries' => Country::orderBy('name')->get(),
            'affiliate_platforms' => $this->affiliatePlatformOptions(),
            'conversionGoals' => ConversionGoal::query()
                ->where('user_id', (int) auth()->id())
                ->where(function ($query) use ($campaign) {
                    $query->where('active', true);

                    if ($campaign->conversion_goal_id) {
                        $query->orWhere('id', $campaign->conversion_goal_id);
                    }
                })
                ->orderBy('goal_code')
                ->get(['id', 'goal_code', 'active'])
                ->map(fn ($goal) => [
                    'id' => $goal->id,
                    'label' => $goal->goal_code,
                    'active' => (bool) $goal->active,
                ]),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCampaignRequest $request, Campaign $campaign)
    {
        $data = $request->validated();

        $campaign->update([
            'name'       => $data['name'],
            'product_url' => $data['product_url'],
            'conversion_goal_id' => $data['conversion_goal_id'] ?? null,
            'status'     => $data['status'],
            'channel_id' => $data['channel_id'],
            'affiliate_platform_id' => $data['affiliate_platform_id'],
            'google_ads_account_id' => $data['google_ads_account_id'],
            'commission_value' => $data['commission_value'] ?? null,
        ]);

        $campaign->countries()->sync($data['countries'] ?? []);

        return redirect()
            ->route('panel.campaigns.index')
            ->with('success', 'Campanha atualizada com sucesso.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Campaign $campaign)
    {
        $campaign->delete();

        return redirect()
            ->route('panel.campaigns.index')
            ->with('success', 'Campanha removida com sucesso.');
    }

    public function tracking_code(Campaign $campaign)
    {
        $platform = AffiliatePlatform::find($campaign->affiliate_platform_id);
        return response()->json([
            'script' => view('tracking.snippet', [
                'code' => $campaign->code,
                'platform' => $platform->slug,
            ])->render(),
        ]);
    }

    public function countries(Campaign $campaign)
    {
        abort_unless((int) $campaign->user_id === (int) auth()->id(), 403);

        $countries = $campaign->countries()
            ->orderBy('name')
            ->get(['countries.id', 'countries.name', 'countries.iso2']);

        return response()->json([
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'countries_count' => $countries->count(),
            ],
            'countries' => $countries,
        ]);
    }

    protected function channelOptions()
    {
        return Channel::query()
            ->withCount('campaigns')
            ->orderByDesc('campaigns_count')
            ->orderBy('name')
            ->get()
            ->map(fn ($channel) => [
                'id' => $channel->id,
                'name' => $channel->name,
                'label' => $channel->name,
                'campaigns_count' => $channel->campaigns_count,
            ]);
    }

    protected function affiliatePlatformOptions()
    {
        return AffiliatePlatform::query()
            ->withCount('campaigns')
            ->orderByDesc('campaigns_count')
            ->orderBy('name')
            ->get()
            ->map(fn ($platform) => [
                'id' => $platform->id,
                'name' => $platform->name,
                'label' => $platform->name,
                'campaigns_count' => $platform->campaigns_count,
            ]);
    }

    protected function campaignDefaults(): array
    {
        $lastCampaign = Campaign::query()
            ->where('user_id', (int) auth()->id())
            ->latest('created_at')
            ->select(['channel_id', 'affiliate_platform_id'])
            ->first();

        return [
            'channel_id' => $lastCampaign?->channel_id,
            'affiliate_platform_id' => $lastCampaign?->affiliate_platform_id,
        ];
    }
}
