<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\BrowserRequest;
use App\Models\Browser;
use Illuminate\Http\Request;

class BrowserController extends Controller
{
    public function index()
    {
        return inertia('Panel/Browsers/Index')
            ->with('title', 'Navegadores');
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

        $query = Browser::query();
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
            ->through(fn (Browser $browser) => $this->transform($browser));

        return response()->json($paginator);
    }

    public function store(BrowserRequest $request)
    {
        $browser = Browser::query()->create($request->validated());

        return response()->json([
            'message' => 'Navegador criado com sucesso.',
            'data' => $this->transform($browser),
        ], 201);
    }

    public function update(BrowserRequest $request, Browser $browser)
    {
        $browser->update($request->validated());

        return response()->json([
            'message' => 'Navegador atualizado com sucesso.',
            'data' => $this->transform($browser->fresh()),
        ]);
    }

    public function destroy(Browser $browser)
    {
        $browser->delete();

        return response()->json([
            'message' => 'Navegador removido com sucesso.',
        ]);
    }

    protected function transform(Browser $browser): array
    {
        return [
            'id' => $browser->id,
            'name' => $browser->name,
            'slug' => $browser->slug,
            'icon_name' => $browser->icon_name,
            'color_hex' => $browser->color_hex,
            'description' => $browser->description,
            'is_system' => (bool) $browser->is_system,
            'is_system_label' => $browser->is_system ? 'Sistema' : 'Customizado',
            'created_at' => optional($browser->created_at)?->toIso8601String(),
            'updated_at' => optional($browser->updated_at)?->toIso8601String(),
        ];
    }
}
