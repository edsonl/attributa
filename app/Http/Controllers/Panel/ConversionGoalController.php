<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\StoreConversionGoalRequest;
use App\Http\Requests\Panel\UpdateConversionGoalRequest;
use App\Models\ConversionGoal;
use Illuminate\Support\Carbon;
use App\Models\Timezone;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConversionGoalController extends Controller
{
    public function index(Request $request)
    {
        $search = (string) $request->string('search')->trim();
        $sort = $request->input('sort', 'created_at');
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = (int) $request->input('per_page', 15);
        $perPage = min(max($perPage, 5), 100);

        $allowedSorts = ['id', 'goal_code', 'active', 'created_at'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $query = ConversionGoal::query()
            ->with([
                'campaigns:id,name,conversion_goal_id',
                'timezone:id,identifier,label,utc_offset',
            ]);

        if ($search !== '') {
            $query->where('goal_code', 'like', "%{$search}%");
        }

        $conversionGoals = $query
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        $conversionGoals->getCollection()->transform(function (ConversionGoal $goal) {
            $goal->integration_url = route('api.google-ads.conversions.goal', [
                'userSlugId' => $goal->user_slug_id,
                'goalCode' => $goal->goal_code,
            ]);

            return $goal;
        });

        return Inertia::render('Panel/ConversionGoals/Index', [
            'conversionGoals' => $conversionGoals,
            'logsRetentionDays' => (int) config('app.conversion_goal_logs_retention_days', 10),
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Panel/ConversionGoals/Create', [
            'timezones' => $this->timezoneOptions(),
        ]);
    }

    public function store(StoreConversionGoalRequest $request)
    {
        $data = $request->validated();

        ConversionGoal::create([
            'user_id' => auth()->id(),
            'goal_code' => $data['goal_code'],
            'timezone_id' => $data['timezone_id'],
            'active' => $data['active'],
        ]);

        return redirect()
            ->route('panel.conversion-goals.index')
            ->with('success', 'Meta de conversão criada com sucesso.');
    }

    public function edit(ConversionGoal $conversionGoal)
    {
        $conversionGoal->load([
            'campaigns' => function ($query) {
                $query->select(['campaigns.id', 'campaigns.name', 'campaigns.conversion_goal_id'])
                    ->orderBy('campaigns.name');
            },
        ]);

        return Inertia::render('Panel/ConversionGoals/Edit', [
            'conversionGoal' => $conversionGoal,
            'timezones' => $this->timezoneOptions(),
        ]);
    }

    public function update(UpdateConversionGoalRequest $request, ConversionGoal $conversionGoal)
    {
        $data = $request->validated();

        $conversionGoal->update([
            'goal_code' => $data['goal_code'],
            'timezone_id' => $data['timezone_id'],
            'active' => $data['active'],
        ]);

        return redirect()
            ->route('panel.conversion-goals.index')
            ->with('success', 'Meta de conversão atualizada com sucesso.');
    }

    public function destroy(ConversionGoal $conversionGoal)
    {
        $conversionGoal->delete();

        return redirect()
            ->route('panel.conversion-goals.index')
            ->with('success', 'Meta de conversão removida com sucesso.');
    }

    public function logs(Request $request, ConversionGoal $conversionGoal)
    {
        if ((int) $conversionGoal->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $sort = $request->input('sort', 'created_at');
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) $request->input('per_page', 15), 5), 100);

        $allowedSorts = ['created_at'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $paginator = $conversionGoal->logs()
            ->orderBy($sort, $direction)
            ->paginate($perPage);

        $paginator->getCollection()->transform(function ($log) {
            $log->created_at_formatted = $log->created_at
                ? Carbon::parse($log->created_at, 'UTC')->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s')
                : null;

            return $log;
        });

        return response()->json($paginator);
    }

    public function destroyLogs(ConversionGoal $conversionGoal)
    {
        if ((int) $conversionGoal->user_id !== (int) auth()->id()) {
            abort(403);
        }

        $deleted = $conversionGoal->logs()->delete();

        return response()->json([
            'message' => 'Logs removidos com sucesso.',
            'deleted' => $deleted,
        ]);
    }

    protected function timezoneOptions()
    {
        return Timezone::query()
            ->where('active', true)
            ->orderBy('utc_offset')
            ->orderBy('identifier')
            ->get(['id', 'identifier', 'label', 'utc_offset'])
            ->map(fn ($timezone) => [
                'id' => $timezone->id,
                'identifier' => $timezone->identifier,
                'label' => $timezone->label,
                'utc_offset' => $timezone->utc_offset,
            ]);
    }
}
