<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Requests\Panel\StoreUserRequest;
use App\Http\Requests\Panel\UpdateUserRequest;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $perPage   = (int) $request->input('per_page', 10);
        $search    = trim((string) $request->input('search', ''));
        $sortBy    = $request->input('sort', 'id');
        $direction = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Whitelist de colunas ordenáveis
        $sortable = ['id', 'name', 'email', 'created_at'];
        if (! in_array($sortBy, $sortable, true)) {
            $sortBy = 'id';
        }

        $query = User::query()
            ->where('id', '!=', 1); // oculta o usuário id=1 da listagem

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $query->orderBy($sortBy, $direction);

        $users = $query->paginate($perPage)
                ->through(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at
                    ? $user->created_at->format('d/m/Y H:i')
                    : null,
            ])->appends($request->query());

        return Inertia::render('Panel/Users/Index', [
            'users' => $users,
            'filters' => [
                'search'    => $search,
                'per_page'  => $perPage,
                'sort'      => $sortBy,
                'direction' => $direction,
            ],
            'meta' => [
                'sortable' => $sortable,
            ],
        ])->with('title', 'Gerenciar usuários');
    }

    public function create()
    {
        return Inertia::render('Panel/Users/Create')
            ->with('title', 'Cadastrar usuário');
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        return redirect()
            ->route('panel.users.edit', $user->id)
            ->with('success', 'Usuário cadastrado com sucesso.');
    }

    public function edit(User $user)
    {
        // Permite editar o id=1? Se quiser também bloquear edição:
        if ($user->id === 1) abort(403);

        return Inertia::render('Panel/Users/Edit', [
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ])->with('title', 'Editar usuário');
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()
            ->route('panel.users.edit', $user->id)
            ->with('success', 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $user)
    {
        if ($user->id === 1) {
            return back()->with('warning', 'Usuário raiz não pode ser removido.');
        }

        $user->delete();

        return back()->with('success', 'Usuário removido com sucesso.');
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        // Garante que id=1 jamais seja deletado
        $ids = collect($data['ids'])->filter(fn ($id) => (int)$id !== 1)->values();

        if ($ids->isEmpty()) {
            return back()->with('warning', 'Nenhum registro válido para exclusão.');
        }

        \App\Models\User::whereIn('id', $ids)->delete();

        return back()->with('success', 'Registros selecionados removidos com sucesso.');
    }

}
