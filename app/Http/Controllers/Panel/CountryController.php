<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\CountryRequest;
use App\Models\Country;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    public function index()
    {
        return inertia('Panel/Countries/Index')
            ->with('title', 'Países');
    }

    public function data(Request $request)
    {
        $perPage    = (int) $request->get('per_page', 10);
        $perPage    = max(1, min($perPage, 100));
        $sortBy     = $request->get('sortBy', 'name');
        $descending = filter_var($request->get('descending', false), FILTER_VALIDATE_BOOLEAN);
        $search     = trim((string) $request->get('search', ''));

        $sortable = [
            'name'             => 'name',
            'iso2'             => 'iso2',
            'iso3'             => 'iso3',
            'currency'         => 'currency',
            'currency_symbol'  => 'currency_symbol',
            'timezone_default' => 'timezone_default',
            'active'           => 'active',
            'created_at'       => 'created_at',
        ];

        $orderColumn = $sortable[$sortBy] ?? 'name';
        $orderDir    = $descending ? 'desc' : 'asc';

        $query = Country::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('iso2', 'like', "%{$search}%")
                    ->orWhere('iso3', 'like', "%{$search}%")
                    ->orWhere('currency', 'like', "%{$search}%");
            });
        }

        $paginator = $query
            ->orderBy($orderColumn, $orderDir)
            ->paginate($perPage)
            ->appends($request->query())
            ->through(fn (Country $country) => $this->transformCountry($country));

        return response()->json($paginator);
    }

    public function store(CountryRequest $request)
    {
        $country = Country::create($request->validated());

        return response()->json([
            'message' => 'País criado com sucesso.',
            'data'    => $this->transformCountry($country),
        ], 201);
    }

    public function update(CountryRequest $request, Country $country)
    {
        $country->update($request->validated());

        return response()->json([
            'message' => 'País atualizado com sucesso.',
            'data'    => $this->transformCountry($country->fresh()),
        ]);
    }

    public function destroy(Country $country)
    {
        $country->delete();

        return response()->json([
            'message' => 'País removido com sucesso.',
        ]);
    }

    protected function transformCountry(Country $country): array
    {
        return [
            'id'               => $country->id,
            'iso2'             => $country->iso2,
            'iso3'             => $country->iso3,
            'name'             => $country->name,
            'currency'         => $country->currency,
            'currency_symbol'  => $country->currency_symbol,
            'timezone_default' => $country->timezone_default,
            'active'           => (bool) $country->active,
            'active_label'     => $country->active ? 'Ativo' : 'Inativo',
            'created_at'       => optional($country->created_at)?->toIso8601String(),
            'updated_at'       => optional($country->updated_at)?->toIso8601String(),
        ];
    }
}
