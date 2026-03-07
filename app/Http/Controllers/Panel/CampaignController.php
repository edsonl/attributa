<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\StoreCampaignRequest;
use App\Http\Requests\Panel\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Models\Channel;
use App\Models\Country;
use App\Models\AffiliatePlatform;
use App\Models\CampaignStatus;
use App\Models\ConversionGoal;
use App\Models\GoogleAdsAccount;
use App\Models\Pageview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

        $allowedSorts = [
            'id' => 'campaigns.id',
            'code' => 'campaigns.code',
            'name' => 'campaigns.name',
            'views' => 'pageviews_count',
            'status' => 'campaigns.campaign_status_id',
            'created_at' => 'campaigns.created_at',
        ];
        if (!array_key_exists($sort, $allowedSorts)) {
            $sort = 'created_at';
        }

        $campaignsQuery = Campaign::with([
            'channel',
            'conversionGoal',
        ])
            ->withCount(['countries', 'pageviews', 'visitors'])
            ->where('user_id', $userId);

        if ($search !== '') {
            $campaignsQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $campaigns = $campaignsQuery
            ->orderBy($allowedSorts[$sort], $direction)
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
            'campaignStatusCatalog' => $this->campaignStatusOptions(),
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
            // 'channels'  => $this->channelOptions(), // oculto por enquanto (canal fixo em Google Ads)
            'countries' => Country::orderBy('name')->get(),
            'affiliate_platforms' => $this->affiliatePlatformOptions(),
            'campaignStatuses' => $this->campaignStatusOptions(),
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
            'googleAdsLeadForm' => [
                'webhook_url' => null,
                'key' => null,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCampaignRequest $request)
    {
        $data = $request->validated();
        $googleAdsChannelId = $this->requireGoogleAdsChannelId();
        $formLeadActive = (bool) ($data['form_lead_active'] ?? false);

        $campaign = Campaign::create([
            'user_id'    => auth()->id(),
            'name'       => $data['name'],
            'product_url' => $data['product_url'],
            'conversion_goal_id' => $data['conversion_goal_id'] ?? null,
            'campaign_status_id' => $data['campaign_status_id'],
            'channel_id' => $googleAdsChannelId,
            'affiliate_platform_id' => $data['affiliate_platform_id'],
            'google_ads_account_id' => $data['google_ads_account_id'],
            'commission_value' => $data['commission_value'] ?? null,
            'stream_code' => $data['stream_code'] ?? null,
            'form_lead_active' => $formLeadActive,
            'google_ads_form_key' => $formLeadActive ? $this->generateGoogleAdsFormKey() : null,
        ]);

        if (!empty($data['countries'])) {
            $campaign->countries()->sync($data['countries']);
        }

        if ($formLeadActive) {
            return redirect()
                ->route('panel.campaigns.edit', $campaign)
                ->with('success', 'Campanha criada com sucesso. Copie a URL e a chave da integração.');
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
            // 'channels'  => $this->channelOptions(), // oculto por enquanto (canal fixo em Google Ads)
            'countries' => Country::orderBy('name')->get(),
            'affiliate_platforms' => $this->affiliatePlatformOptions(),
            'campaignStatuses' => $this->campaignStatusOptions(),
            'defaults' => $this->campaignDefaults(),
            'googleAdsLeadForm' => $this->googleAdsLeadFormData($campaign),
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
    public function update(
        UpdateCampaignRequest $request,
        Campaign $campaign
    )
    {
        $data = $request->validated();
        $googleAdsChannelId = $this->requireGoogleAdsChannelId();
        $formLeadActive = (bool) ($data['form_lead_active'] ?? false);
        $googleAdsFormKey = $campaign->google_ads_form_key;

        if ($formLeadActive && empty($googleAdsFormKey)) {
            $googleAdsFormKey = $this->generateGoogleAdsFormKey();
        }

        $campaign->update([
            'name'       => $data['name'],
            'product_url' => $data['product_url'],
            'conversion_goal_id' => $data['conversion_goal_id'] ?? null,
            'campaign_status_id' => $data['campaign_status_id'],
            'channel_id' => $googleAdsChannelId,
            'affiliate_platform_id' => $data['affiliate_platform_id'],
            'google_ads_account_id' => $data['google_ads_account_id'],
            'commission_value' => $data['commission_value'] ?? null,
            'stream_code' => $data['stream_code'] ?? null,
            'form_lead_active' => $formLeadActive,
            'google_ads_form_key' => $googleAdsFormKey,
        ]);

        $campaign->countries()->sync($data['countries'] ?? []);
        $this->invalidateTrackingCampaignCache((int) $campaign->id);

        return redirect()
            ->route('panel.campaigns.index')
            ->with('success', 'Campanha atualizada com sucesso.');
    }

    public function regenerateGoogleAdsFormKey(Campaign $campaign)
    {
        if (!(bool) $campaign->form_lead_active) {
            return response()->json([
                'message' => 'Ative o formulário de lead para gerar a chave.',
            ], 422);
        }

        $newKey = $this->generateGoogleAdsFormKey();
        $campaign->update([
            'google_ads_form_key' => $newKey,
        ]);

        return response()->json([
            'message' => 'Chave de integração atualizada com sucesso.',
            'google_ads_form_key' => $newKey,
            'webhook_url' => $this->buildGoogleAdsFormWebhookUrl($campaign),
        ]);
    }

    public function updateGoogleAdsLeadFormState(Request $request, Campaign $campaign)
    {
        $data = $request->validate([
            'active' => ['required', 'boolean'],
            'stream_code' => ['nullable', 'string', 'max:30', 'regex:/^\S+$/'],
        ], [
            'stream_code.max' => 'O código stream não pode ter mais de 30 caracteres.',
            'stream_code.regex' => 'O código stream não pode conter espaços.',
        ]);

        $active = (bool) $data['active'];
        $streamCode = array_key_exists('stream_code', $data)
            ? trim((string) $data['stream_code'])
            : (string) ($campaign->stream_code ?? '');

        $streamCode = $streamCode === '' ? null : $streamCode;

        if ($active && !$streamCode) {
            return response()->json([
                'message' => 'Informe o código stream para ativar o formulário de lead.',
            ], 422);
        }

        $campaign->stream_code = $streamCode;
        $campaign->form_lead_active = $active;
        $campaign->google_ads_form_key = $active
            ? ($campaign->google_ads_form_key ?: $this->generateGoogleAdsFormKey())
            : $campaign->google_ads_form_key;
        $campaign->save();

        return response()->json([
            'message' => $active
                ? 'Formulário de lead ativado com sucesso.'
                : 'Formulário de lead desativado com sucesso.',
            'form_lead_active' => (bool) $campaign->form_lead_active,
            'stream_code' => $campaign->stream_code,
            'google_ads_form_key' => $campaign->google_ads_form_key,
            'webhook_url' => $this->buildGoogleAdsFormWebhookUrl($campaign),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Campaign $campaign)
    {
        $campaignId = (int) $campaign->id;

        DB::transaction(function () use ($campaign) {
            Pageview::query()
                ->where('campaign_id', (int) $campaign->id)
                ->where('user_id', (int) $campaign->user_id)
                ->delete();

            $campaign->delete();
        });

        $this->invalidateTrackingCampaignCache($campaignId);

        return redirect()
            ->route('panel.campaigns.index')
            ->with('success', 'Campanha removida com sucesso.');
    }

    public function tracking_code(Campaign $campaign)
    {
        return response()->json([
            'script' => view('tracking.snippet', [
                'userCode' => app(\App\Services\HashidService::class)->encode((int) $campaign->user_id),
                'campaignCode' => $campaign->code,
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

    public function toggleStatus(Campaign $campaign)
    {
        $currentSlug = (string) optional($campaign->campaignStatus)->slug;
        $targetSlug = $currentSlug === 'active' ? 'paused' : 'active';

        $targetStatus = CampaignStatus::query()
            ->where('slug', $targetSlug)
            ->where('active', true)
            ->first();

        if (!$targetStatus) {
            return response()->json([
                'message' => 'Status de destino não encontrado.',
            ], 422);
        }

        $campaign->campaign_status_id = $targetStatus->id;
        $campaign->save();
        $this->invalidateTrackingCampaignCache((int) $campaign->id);

        return response()->json([
            'message' => 'Status da campanha atualizado com sucesso.',
            'campaign' => [
                'id' => $campaign->id,
                'campaign_status_id' => $targetStatus->id,
            ],
        ]);
    }

    public function updateStatus(Request $request, Campaign $campaign)
    {
        $data = $request->validate([
            'campaign_status_id' => ['required', 'integer'],
        ]);

        $targetStatus = CampaignStatus::query()
            ->where('id', (int) $data['campaign_status_id'])
            ->where('active', true)
            ->first();

        if (!$targetStatus) {
            return response()->json([
                'message' => 'Status de campanha inválido.',
            ], 422);
        }

        $campaign->campaign_status_id = $targetStatus->id;
        $campaign->save();
        $this->invalidateTrackingCampaignCache((int) $campaign->id);

        return response()->json([
            'message' => 'Status da campanha atualizado com sucesso.',
            'campaign' => [
                'id' => $campaign->id,
                'campaign_status_id' => $targetStatus->id,
            ],
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
        $googleAdsChannelId = $this->googleAdsChannelId();

        $lastCampaign = Campaign::query()
            ->where('user_id', (int) auth()->id())
            ->latest('created_at')
            ->select(['affiliate_platform_id'])
            ->first();

        $defaultStatusId = CampaignStatus::query()
            ->where('slug', 'active')
            ->value('id');

        return [
            'channel_id' => $googleAdsChannelId,
            'affiliate_platform_id' => $lastCampaign?->affiliate_platform_id,
            'campaign_status_id' => $defaultStatusId,
            'form_lead_active' => false,
        ];
    }

    protected function generateGoogleAdsFormKey(): string
    {
        return Str::random(32);
    }

    protected function googleAdsLeadFormData(Campaign $campaign): array
    {
        if ((bool) $campaign->form_lead_active && empty($campaign->google_ads_form_key)) {
            $campaign->google_ads_form_key = $this->generateGoogleAdsFormKey();
            $campaign->saveQuietly();
        }

        return [
            'webhook_url' => $this->buildGoogleAdsFormWebhookUrl($campaign),
            'key' => $campaign->google_ads_form_key,
        ];
    }

    protected function buildGoogleAdsFormWebhookUrl(Campaign $campaign): string
    {
        return route('google-ads.form.handle', [
            'userHash' => app(\App\Services\HashidService::class)->encode((int) $campaign->user_id),
            'campaignHash' => $campaign->hashid,
        ]);
    }

    protected function googleAdsChannelId(): ?int
    {
        $id = Channel::query()
            ->where('slug', 'google_ads')
            ->value('id');

        return $id ? (int) $id : null;
    }

    protected function requireGoogleAdsChannelId(): int
    {
        $id = $this->googleAdsChannelId();

        if (!$id) {
            throw ValidationException::withMessages([
                'channel_id' => 'Canal Google Ads não configurado. Rode o seed de canais.',
            ]);
        }

        return $id;
    }

    protected function campaignStatusOptions()
    {
        return CampaignStatus::query()
            ->where('active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'color_hex'])
            ->map(fn ($status) => [
                'id' => $status->id,
                'name' => $status->name,
                'slug' => $status->slug,
                'color_hex' => $status->color_hex,
                'label' => $status->name,
                'tooltip' => $status->description ?: ('Status: ' . $status->name),
            ]);
    }

    /**
     * Invalida o cache Redis de metadados da campanha usado no tracking collect.
     */
    protected function invalidateTrackingCampaignCache(int $campaignId): void
    {
        if ($campaignId < 1) {
            return;
        }

        $connection = (string) config('tracking.redis.connection', 'tracking');
        $prefix = trim((string) config('tracking.redis.prefix', 'tracking'));
        $key = $prefix . ':campaign:' . $campaignId;

        Redis::connection($connection)->del($key);
    }
}
