<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\CampaignStatusRequest;
use App\Models\CampaignStatus;
use Illuminate\Http\Request;

class CampaignStatusController extends Controller
{
    public function index()
    {
        return inertia('Panel/CampaignStatuses/Index')
            ->with('title', 'Status de Campanha');
    }

    public function data(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $perPage = max(1, min($perPage, 100));
        $sortBy = $request->get('sortBy', 'name');
        $descending = filter_var($request->get('descending', false), FILTER_VALIDATE_BOOLEAN);
        $search = trim((string) $request->get('search', ''));

        $sortable = [
            'name' => 'name',
            'slug' => 'slug',
            'active' => 'active',
            'is_system' => 'is_system',
            'created_at' => 'created_at',
        ];

        $orderColumn = $sortable[$sortBy] ?? 'name';
        $orderDir = $descending ? 'desc' : 'asc';

        $query = CampaignStatus::query();
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $paginator = $query
            ->orderBy($orderColumn, $orderDir)
            ->paginate($perPage)
            ->appends($request->query())
            ->through(fn (CampaignStatus $campaignStatus) => $this->transform($campaignStatus));

        return response()->json($paginator);
    }

    public function store(CampaignStatusRequest $request)
    {
        $campaignStatus = CampaignStatus::query()->create($request->validated());

        return response()->json([
            'message' => 'Status de campanha criado com sucesso.',
            'data' => $this->transform($campaignStatus),
        ], 201);
    }

    public function update(CampaignStatusRequest $request, CampaignStatus $campaignStatus)
    {
        $campaignStatus->update($request->validated());

        return response()->json([
            'message' => 'Status de campanha atualizado com sucesso.',
            'data' => $this->transform($campaignStatus->fresh()),
        ]);
    }

    public function destroy(CampaignStatus $campaignStatus)
    {
        if ((bool) $campaignStatus->is_system) {
            return response()->json([
                'message' => 'Status de sistema não pode ser removido.',
            ], 422);
        }

        if ($campaignStatus->campaigns()->exists()) {
            return response()->json([
                'message' => 'Este status está vinculado a campanhas e não pode ser removido.',
            ], 422);
        }

        $campaignStatus->delete();

        return response()->json([
            'message' => 'Status de campanha removido com sucesso.',
        ]);
    }

    protected function transform(CampaignStatus $campaignStatus): array
    {
        return [
            'id' => $campaignStatus->id,
            'name' => $campaignStatus->name,
            'slug' => $campaignStatus->slug,
            'color_hex' => $campaignStatus->color_hex,
            'description' => $campaignStatus->description,
            'is_system' => (bool) $campaignStatus->is_system,
            'is_system_label' => $campaignStatus->is_system ? 'Sistema' : 'Customizado',
            'active' => (bool) $campaignStatus->active,
            'active_label' => $campaignStatus->active ? 'Ativo' : 'Inativo',
            'created_at' => optional($campaignStatus->created_at)?->toIso8601String(),
            'updated_at' => optional($campaignStatus->updated_at)?->toIso8601String(),
        ];
    }
}

