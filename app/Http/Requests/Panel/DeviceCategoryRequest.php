<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'slug' => strtolower(trim((string) $this->input('slug'))),
            'icon_name' => trim((string) $this->input('icon_name', '')) ?: null,
            'color_hex' => strtoupper(trim((string) $this->input('color_hex', ''))) ?: null,
            'description' => $this->input('description'),
            'is_system' => filter_var($this->input('is_system', true), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function rules(): array
    {
        $deviceCategoryId = $this->route('device_category')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('device_categories', 'slug')->ignore($deviceCategoryId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon_name' => ['nullable', 'string', 'max:100'],
            'color_hex' => ['nullable', 'regex:/^#[0-9A-F]{6}$/'],
            'is_system' => ['boolean'],
        ];
    }
}
