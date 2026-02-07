<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\StoreCampaignRequest;
use App\Http\Requests\Panel\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Models\Channel;
use App\Models\Country;
use App\Models\AffiliatePlatform;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CampaignController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = (string) $request->string('search')->trim();
        $sort = $request->input('sort', 'created_at');
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = (int) $request->input('per_page', 15);
        $perPage = min(max($perPage, 5), 100);

        $allowedSorts = ['id', 'code', 'name', 'status', 'created_at'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $campaignsQuery = Campaign::with(['channel', 'countries']);

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
            'channels'  => Channel::orderBy('name')->get(),
            'countries' => Country::orderBy('name')->get(),
            'affiliate_platforms' => AffiliatePlatform::orderBy('id')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCampaignRequest $request)
    {
        $data = $request->validated();

        $campaign = Campaign::create([
            'name'       => $data['name'],
            'pixel_code' => $data['pixel_code'],
            'status'     => $data['status'],
            'channel_id' => $data['channel_id'],
            'affiliate_platform_id' => $data['affiliate_platform_id'],
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
            'campaign'  => $campaign->load('countries'),
            'channels'  => Channel::orderBy('name')->get(),
            'countries' => Country::orderBy('name')->get(),
            'affiliate_platforms' => AffiliatePlatform::orderBy('id')->get(),
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
            'pixel_code' => $data['pixel_code'],
            'status'     => $data['status'],
            'channel_id' => $data['channel_id'],
            'affiliate_platform_id' => $data['affiliate_platform_id'],
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
}
