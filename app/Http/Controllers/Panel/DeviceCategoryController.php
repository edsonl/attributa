<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\DeviceCategoryRequest;
use App\Models\DeviceCategory;
use Illuminate\Http\Request;

class DeviceCategoryController extends Controller
{
    public function index()
    {
        return inertia('Panel/DeviceCategories/Index')
            ->with('title', 'Categorias de Dispositivo');
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

        $query = DeviceCategory::query();
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
            ->through(fn (DeviceCategory $deviceCategory) => $this->transform($deviceCategory));

        return response()->json($paginator);
    }

    public function store(DeviceCategoryRequest $request)
    {
        $deviceCategory = DeviceCategory::query()->create($request->validated());

        return response()->json([
            'message' => 'Categoria de dispositivo criada com sucesso.',
            'data' => $this->transform($deviceCategory),
        ], 201);
    }

    public function update(DeviceCategoryRequest $request, DeviceCategory $deviceCategory)
    {
        $deviceCategory->update($request->validated());

        return response()->json([
            'message' => 'Categoria de dispositivo atualizada com sucesso.',
            'data' => $this->transform($deviceCategory->fresh()),
        ]);
    }

    public function destroy(DeviceCategory $deviceCategory)
    {
        $deviceCategory->delete();

        return response()->json([
            'message' => 'Categoria de dispositivo removida com sucesso.',
        ]);
    }

    protected function transform(DeviceCategory $deviceCategory): array
    {
        return [
            'id' => $deviceCategory->id,
            'name' => $deviceCategory->name,
            'slug' => $deviceCategory->slug,
            'icon_name' => $deviceCategory->icon_name,
            'color_hex' => $deviceCategory->color_hex,
            'description' => $deviceCategory->description,
            'is_system' => (bool) $deviceCategory->is_system,
            'is_system_label' => $deviceCategory->is_system ? 'Sistema' : 'Customizado',
            'created_at' => optional($deviceCategory->created_at)?->toIso8601String(),
            'updated_at' => optional($deviceCategory->updated_at)?->toIso8601String(),
        ];
    }
}
