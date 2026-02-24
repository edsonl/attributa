<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\NotificationCategory;
use App\Models\NotificationType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class NotificationCatalogController extends Controller
{
    public function index()
    {
        return Inertia::render('Panel/NotificationCatalog/Index')
            ->with('title', 'Catálogo de notificações');
    }

    public function data()
    {
        $categories = NotificationCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (NotificationCategory $category) {
                $types = NotificationType::query()
                    ->where('notification_category_id', $category->id)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (NotificationType $type) => $this->transformType($type))
                    ->values();

                return $this->transformCategory($category) + [
                    'types' => $types,
                ];
            })
            ->values();

        return response()->json([
            'categories' => $categories,
            'severity_options' => NotificationType::SEVERITIES,
        ]);
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', Rule::unique('notification_categories', 'slug')],
            'description' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $category = NotificationCategory::query()->create([
            'name' => $data['name'],
            'slug' => strtolower(trim((string) $data['slug'])),
            'description' => $data['description'] ?? null,
            'active' => (bool) ($data['active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return response()->json([
            'message' => 'Categoria cadastrada com sucesso.',
            'data' => $this->transformCategory($category),
        ], 201);
    }

    public function updateCategory(Request $request, NotificationCategory $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', Rule::unique('notification_categories', 'slug')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $category->update([
            'name' => $data['name'],
            'slug' => strtolower(trim((string) $data['slug'])),
            'description' => $data['description'] ?? null,
            'active' => (bool) ($data['active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return response()->json([
            'message' => 'Categoria atualizada com sucesso.',
            'data' => $this->transformCategory($category->fresh()),
        ]);
    }

    public function destroyCategory(NotificationCategory $category)
    {
        if (NotificationType::query()->where('notification_category_id', $category->id)->exists()) {
            return response()->json([
                'message' => 'Não é possível remover: existem tipos de notificação vinculados.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Categoria removida com sucesso.',
        ]);
    }

    public function storeType(Request $request)
    {
        $data = $request->validate([
            'notification_category_id' => ['required', 'integer', Rule::exists('notification_categories', 'id')],
            'name' => ['required', 'string', 'max:140'],
            'slug' => ['required', 'string', 'max:90', 'regex:/^[a-z0-9_]+$/', Rule::unique('notification_types', 'slug')],
            'description' => ['nullable', 'string', 'max:255'],
            'default_title' => ['nullable', 'string', 'max:180'],
            'default_message' => ['nullable', 'string'],
            'severity' => ['required', 'string', Rule::in(NotificationType::SEVERITIES)],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $type = NotificationType::query()->create([
            'notification_category_id' => (int) $data['notification_category_id'],
            'name' => $data['name'],
            'slug' => strtolower(trim((string) $data['slug'])),
            'description' => $data['description'] ?? null,
            'default_title' => $data['default_title'] ?? null,
            'default_message' => $data['default_message'] ?? null,
            'severity' => strtolower(trim((string) $data['severity'])),
            'active' => (bool) ($data['active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return response()->json([
            'message' => 'Tipo de notificação cadastrado com sucesso.',
            'data' => $this->transformType($type),
        ], 201);
    }

    public function updateType(Request $request, NotificationType $type)
    {
        $data = $request->validate([
            'notification_category_id' => ['required', 'integer', Rule::exists('notification_categories', 'id')],
            'name' => ['required', 'string', 'max:140'],
            'slug' => ['required', 'string', 'max:90', 'regex:/^[a-z0-9_]+$/', Rule::unique('notification_types', 'slug')->ignore($type->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'default_title' => ['nullable', 'string', 'max:180'],
            'default_message' => ['nullable', 'string'],
            'severity' => ['required', 'string', Rule::in(NotificationType::SEVERITIES)],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $type->update([
            'notification_category_id' => (int) $data['notification_category_id'],
            'name' => $data['name'],
            'slug' => strtolower(trim((string) $data['slug'])),
            'description' => $data['description'] ?? null,
            'default_title' => $data['default_title'] ?? null,
            'default_message' => $data['default_message'] ?? null,
            'severity' => strtolower(trim((string) $data['severity'])),
            'active' => (bool) ($data['active'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return response()->json([
            'message' => 'Tipo de notificação atualizado com sucesso.',
            'data' => $this->transformType($type->fresh()),
        ]);
    }

    public function destroyType(NotificationType $type)
    {
        $type->delete();

        return response()->json([
            'message' => 'Tipo de notificação removido com sucesso.',
        ]);
    }

    protected function transformCategory(NotificationCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'active' => (bool) $category->active,
            'sort_order' => (int) $category->sort_order,
            'created_at' => optional($category->created_at)?->toIso8601String(),
            'updated_at' => optional($category->updated_at)?->toIso8601String(),
        ];
    }

    protected function transformType(NotificationType $type): array
    {
        return [
            'id' => $type->id,
            'notification_category_id' => (int) $type->notification_category_id,
            'name' => $type->name,
            'slug' => $type->slug,
            'description' => $type->description,
            'default_title' => $type->default_title,
            'default_message' => $type->default_message,
            'severity' => $type->severity,
            'active' => (bool) $type->active,
            'sort_order' => (int) $type->sort_order,
            'created_at' => optional($type->created_at)?->toIso8601String(),
            'updated_at' => optional($type->updated_at)?->toIso8601String(),
        ];
    }
}

