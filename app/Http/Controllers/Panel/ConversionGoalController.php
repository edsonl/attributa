<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\StoreConversionGoalRequest;
use App\Http\Requests\Panel\UpdateConversionGoalRequest;
use App\Models\ConversionGoal;
use Illuminate\Support\Carbon;
use App\Models\Timezone;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ConversionGoalController extends Controller
{
    public function index(Request $request)
    {
        $userId = (int) auth()->id();
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
            ->where('user_id', $userId)
            ->with([
                'campaigns' => function ($query) use ($userId) {
                    $query->where('campaigns.user_id', $userId)
                        ->select(['campaigns.id', 'campaigns.name', 'campaigns.conversion_goal_id']);
                },
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
            'defaultTimezoneId' => $this->defaultTimezoneId(),
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
            'csv_fake_line_enabled' => false,
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
                    ->where('campaigns.user_id', (int) auth()->id())
                    ->orderBy('campaigns.name');
            },
        ]);

        return Inertia::render('Panel/ConversionGoals/Edit', [
            'conversionGoal' => $conversionGoal,
            'timezones' => $this->timezoneOptions((int) $conversionGoal->timezone_id),
            'defaultTimezoneId' => $this->defaultTimezoneId(),
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
        $deleted = $conversionGoal->logs()->delete();

        return response()->json([
            'message' => 'Logs removidos com sucesso.',
            'deleted' => $deleted,
        ]);
    }

    public function regeneratePassword(ConversionGoal $conversionGoal)
    {
        abort_unless((int) $conversionGoal->user_id === (int) auth()->id(), 403);

        $newPassword = Str::random(25);
        $conversionGoal->update([
            'googleads_password' => $newPassword,
        ]);

        return response()->json([
            'message' => 'Senha de integração atualizada com sucesso.',
            'googleads_password' => $newPassword,
        ]);
    }

    public function snapshot(ConversionGoal $conversionGoal)
    {
        abort_unless((int) $conversionGoal->user_id === (int) auth()->id(), 403);

        $snapshot = $conversionGoal->csvSnapshot()->first();
        $header = $snapshot?->snapshot_json['header'] ?? [];
        $rows = $snapshot?->snapshot_json['rows'] ?? [];

        if (is_array($header)) {
            $normalized = $this->removeUserAgentFromSnapshot($header, is_array($rows) ? $rows : []);
            $header = $normalized['header'];
            $rows = $normalized['rows'];
        }

        return response()->json([
            'goal_id' => $conversionGoal->id,
            'goal_code' => $conversionGoal->goal_code,
            'has_snapshot' => $snapshot !== null,
            'rows_count' => (int) ($snapshot?->rows_count ?? 0),
            'header' => is_array($header) ? array_values($header) : [],
            'rows' => is_array($rows) ? array_values($rows) : [],
            'updated_at' => $snapshot?->updated_at,
            'updated_at_formatted' => $snapshot?->updated_at
                ? Carbon::parse($snapshot->updated_at, 'UTC')->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s')
                : null,
        ]);
    }

    public function snapshotCsv(ConversionGoal $conversionGoal)
    {
        abort_unless((int) $conversionGoal->user_id === (int) auth()->id(), 403);

        $snapshot = $conversionGoal->csvSnapshot()->first();
        if (!$snapshot) {
            return response()->json([
                'message' => 'Nenhum snapshot disponível para download.',
            ], 404);
        }

        $header = $snapshot->snapshot_json['header'] ?? [];
        $rows = $snapshot->snapshot_json['rows'] ?? [];

        if (is_array($header)) {
            $normalized = $this->removeUserAgentFromSnapshot($header, is_array($rows) ? $rows : []);
            $header = $normalized['header'];
            $rows = $normalized['rows'];
        }

        if (!is_array($header) || count($header) === 0) {
            return response()->json([
                'message' => 'Snapshot inválido: cabeçalho não encontrado.',
            ], 422);
        }

        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_values($header));

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row)) {
                    fputcsv($output, array_values($row));
                }
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        $filename = 'snapshot_' . $conversionGoal->goal_code . '_' . now()->format('Ymd_His') . '.csv';

        return response((string) $csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    protected function timezoneOptions(?int $includeTimezoneId = null)
    {
        return Timezone::query()
            ->where(function ($query) use ($includeTimezoneId) {
                $query->where('active', true);

                if (!empty($includeTimezoneId)) {
                    $query->orWhere('id', $includeTimezoneId);
                }
            })
            ->orderBy('utc_offset')
            ->orderBy('identifier')
            ->get(['id', 'identifier', 'label', 'utc_offset'])
            ->map(fn ($timezone) => [
                'id' => $timezone->id,
                'identifier' => $timezone->identifier,
                'label' => $timezone->label ?: $timezone->identifier,
                'utc_offset' => $timezone->utc_offset,
            ]);
    }

    protected function removeUserAgentFromSnapshot(array $header, array $rows): array
    {
        $userAgentIndex = null;
        foreach ($header as $index => $column) {
            if (mb_strtolower(trim((string) $column)) === 'user agent') {
                $userAgentIndex = $index;
                break;
            }
        }

        if ($userAgentIndex === null) {
            return [
                'header' => array_values($header),
                'rows' => array_values($rows),
            ];
        }

        unset($header[$userAgentIndex]);
        $normalizedRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            unset($row[$userAgentIndex]);
            $normalizedRows[] = array_values($row);
        }

        return [
            'header' => array_values($header),
            'rows' => $normalizedRows,
        ];
    }

    protected function defaultTimezoneId(): ?int
    {
        $preferred = Timezone::query()
            ->where('identifier', 'America/Sao_Paulo')
            ->where('active', true)
            ->value('id');

        if ($preferred) {
            return (int) $preferred;
        }

        $fallback = Timezone::query()
            ->where('active', true)
            ->orderBy('utc_offset')
            ->orderBy('identifier')
            ->value('id');

        return $fallback ? (int) $fallback : null;
    }
}
