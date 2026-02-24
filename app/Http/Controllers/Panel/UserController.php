<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\NotificationCategory;
use App\Models\NotificationType;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Requests\Panel\StoreUserRequest;
use App\Http\Requests\Panel\UpdateUserRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

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
                'notification_email' => $user->notification_email,
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
        return Inertia::render('Panel/Users/Create', [
            'notification_options' => $this->notificationOptions(),
            'notification_preferences' => [],
        ])->with('title', 'Cadastrar usuário');
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $preferences = $data['notification_preferences'] ?? [];
        unset($data['notification_preferences']);

        $data['password'] = Hash::make($data['password']);
        $user = DB::transaction(function () use ($data, $preferences) {
            $created = User::create($data);
            $this->syncNotificationPreferences($created, $preferences);
            return $created;
        });

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
                'notification_email' => $user->notification_email,
            ],
            'notification_options' => $this->notificationOptions(),
            'notification_preferences' => $this->userNotificationPreferences($user->id),
        ])->with('title', 'Editar usuário');
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();
        $preferences = $data['notification_preferences'] ?? [];
        unset($data['notification_preferences']);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        DB::transaction(function () use ($user, $data, $preferences) {
            $user->update($data);
            $this->syncNotificationPreferences($user, $preferences);
        });

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

    protected function notificationOptions(): array
    {
        return NotificationCategory::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (NotificationCategory $category) {
                $types = NotificationType::query()
                    ->where('notification_category_id', $category->id)
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'slug', 'severity'])
                    ->map(fn (NotificationType $type) => [
                        'id' => (int) $type->id,
                        'name' => $type->name,
                        'slug' => $type->slug,
                        'severity' => $type->severity,
                    ])
                    ->values();

                return [
                    'id' => (int) $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'types' => $types,
                ];
            })
            ->values()
            ->all();
    }

    protected function userNotificationPreferences(int $userId): array
    {
        return UserNotificationPreference::query()
            ->where('user_id', $userId)
            ->get(['notification_type_id', 'enabled_in_app', 'enabled_email', 'enabled_push', 'frequency'])
            ->map(fn (UserNotificationPreference $pref) => [
                'notification_type_id' => (int) $pref->notification_type_id,
                'enabled_in_app' => (bool) $pref->enabled_in_app,
                'enabled_email' => (bool) $pref->enabled_email,
                'enabled_push' => (bool) $pref->enabled_push,
                'frequency' => $pref->frequency,
            ])
            ->values()
            ->all();
    }

    protected function syncNotificationPreferences(User $user, array $preferences): void
    {
        $validTypeIds = NotificationType::query()
            ->whereIn('id', collect($preferences)->pluck('notification_type_id')->filter()->unique()->values())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $validTypeIdSet = array_fill_keys($validTypeIds, true);

        UserNotificationPreference::query()->where('user_id', $user->id)->delete();

        $rows = [];
        foreach ($preferences as $pref) {
            $typeId = (int) ($pref['notification_type_id'] ?? 0);
            if ($typeId <= 0 || !isset($validTypeIdSet[$typeId])) {
                continue;
            }

            $rows[] = [
                'user_id' => $user->id,
                'notification_type_id' => $typeId,
                'enabled_in_app' => (bool) ($pref['enabled_in_app'] ?? true),
                'enabled_email' => (bool) ($pref['enabled_email'] ?? false),
                'enabled_push' => (bool) ($pref['enabled_push'] ?? false),
                'frequency' => isset($pref['frequency']) ? (string) $pref['frequency'] : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($rows)) {
            UserNotificationPreference::query()->insert($rows);
        }
    }

}
