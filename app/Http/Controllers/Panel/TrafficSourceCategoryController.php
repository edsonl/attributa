<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\TrafficSourceCategoryRequest;
use App\Models\TrafficSourceCategory;
use Illuminate\Http\Request;

class TrafficSourceCategoryController extends Controller
{
    public function index()
    {
        return inertia('Panel/TrafficSourceCategories/Index')
            ->with('title', 'Categorias de Origem de Tráfego');
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
            'is_system' => 'is_system',
            'created_at' => 'created_at',
        ];

        $orderColumn = $sortable[$sortBy] ?? 'name';
        $orderDir = $descending ? 'desc' : 'asc';

        $query = TrafficSourceCategory::query();
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
            ->through(fn (TrafficSourceCategory $trafficSourceCategory) => $this->transform($trafficSourceCategory));

        return response()->json($paginator);
    }

    public function store(TrafficSourceCategoryRequest $request)
    {
        $trafficSourceCategory = TrafficSourceCategory::query()->create($request->validated());

        return response()->json([
            'message' => 'Categoria de origem criada com sucesso.',
            'data' => $this->transform($trafficSourceCategory),
        ], 201);
    }

    public function update(TrafficSourceCategoryRequest $request, TrafficSourceCategory $trafficSourceCategory)
    {
        $trafficSourceCategory->update($request->validated());

        return response()->json([
            'message' => 'Categoria de origem atualizada com sucesso.',
            'data' => $this->transform($trafficSourceCategory->fresh()),
        ]);
    }

    public function destroy(TrafficSourceCategory $trafficSourceCategory)
    {
        $trafficSourceCategory->delete();

        return response()->json([
            'message' => 'Categoria de origem removida com sucesso.',
        ]);
    }

    protected function transform(TrafficSourceCategory $trafficSourceCategory): array
    {
        return [
            'id' => $trafficSourceCategory->id,
            'name' => $trafficSourceCategory->name,
            'slug' => $trafficSourceCategory->slug,
            'icon_name' => $trafficSourceCategory->icon_name,
            'color_hex' => $trafficSourceCategory->color_hex,
            'description' => $trafficSourceCategory->description,
            'is_system' => (bool) $trafficSourceCategory->is_system,
            'is_system_label' => $trafficSourceCategory->is_system ? 'Sistema' : 'Customizado',
            'created_at' => optional($trafficSourceCategory->created_at)?->toIso8601String(),
            'updated_at' => optional($trafficSourceCategory->updated_at)?->toIso8601String(),
        ];
    }
}
