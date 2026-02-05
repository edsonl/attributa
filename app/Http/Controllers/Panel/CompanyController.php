<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\CompanyStoreRequest;
use App\Http\Requests\Panel\CompanyUpdateRequest;
use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function __construct()
    {
        //$this->middleware(['auth','verified']);
    }

    public function index(Request $request)
    {
        $q        = (string) $request->input('search', '');
        $sort     = in_array($request->input('sort'), ['name','corporate_name','email','cnpj','created_at']) ? $request->input('sort') : 'created_at';
        $dir      = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $perPage  = (int) $request->integer('per_page', 10);

        $query = Company::query();

        if ($q) {
            $query->where(function($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('corporate_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('cnpj', 'like', "%{$q}%")
                    ->orWhere('whatsapp', 'like', "%{$q}%");
            });
        }

        $query->orderBy($sort, $dir);

        $companies = $query->paginate($perPage)->withQueryString()
            ->through(function ($c) {
                return [
                    'id'             => $c->id,
                    'name'           => $c->name,
                    'corporate_name' => $c->corporate_name,
                    'phone'          => $c->phone,
                    'whatsapp'       => $c->whatsapp,
                    'email'          => $c->email,
                    'site'           => $c->site,
                    'cnpj'           => $c->cnpj,
                    //'notes'          => $c->notes,
                    'created_at'     => optional($c->created_at)->format('d/m/Y H:i'),
                ];
            });

        return Inertia::render('Panel/Companies/Index', [
            'companies'        => $companies,
            'filters'          => $request->only(['q']),
            'defaultSort'      => $sort,
            'defaultDirection' => $dir,
        ]);
    }

    public function create()
    {
        return Inertia::render('Panel/Companies/Create',[]);
    }

    public function store(CompanyStoreRequest $request)
    {
        Company::create($request->validated());
        return redirect()->route('panel.companies.index')->with('success', 'Empresa cadastrada com sucesso.');
    }

    public function edit(Company $company)
    {
        return Inertia::render('Panel/Companies/Edit', [
            'company' => $company->only([
                'id','name','corporate_name','cnpj','phone','whatsapp','email','site','notes'
            ]),
        ]);
    }

    public function update(CompanyUpdateRequest $request, Company $company)
    {
        $company->update($request->validated());
        return redirect()->route('panel.companies.index')->with('success', 'Empresa atualizada com sucesso.');
    }

    public function destroy(Company $company)
    {
        $company->delete();
        return redirect()->back()->with('success', 'Empresa excluída.');
    }
}
