<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\NotificationCategory;
use App\Models\NotificationType;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AccountController extends Controller
{
    // GET /account (editar)
    public function edit(Request $request)
    {
        return Inertia::render('Auth/EditAccount', [
            'title' => 'Editar conta',
            'notification_options' => $this->notificationOptions(),
            'notification_preferences' => $this->userNotificationPreferences((int) $request->user()->id),
        ]);
    }

    // PUT /account (atualizar)
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:191'],
            'email'    => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'notification_email' => ['nullable', 'email', 'max:191'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'notification_preferences' => ['nullable', 'array'],
            'notification_preferences.*.notification_type_id' => ['required', 'integer', Rule::exists('notification_types', 'id')],
            'notification_preferences.*.enabled_in_app' => ['nullable', 'boolean'],
            'notification_preferences.*.enabled_email' => ['nullable', 'boolean'],
            'notification_preferences.*.enabled_push' => ['nullable', 'boolean'],
            'notification_preferences.*.frequency' => ['nullable', 'string', 'max:20'],
        ]);

        $preferences = $validated['notification_preferences'] ?? [];
        unset($validated['notification_preferences']);

        DB::transaction(function () use ($user, $validated, $preferences) {
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->notification_email = $validated['notification_email'] ?? null;

            if (!empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }

            $user->save();
            $this->syncNotificationPreferences($user, $preferences);
        });

        return back()->with('success', 'Dados atualizados com sucesso.');
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
