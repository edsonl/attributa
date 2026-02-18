<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignStatusRequest extends FormRequest
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
            'color_hex' => strtoupper(trim((string) $this->input('color_hex', ''))) ?: null,
            'description' => $this->input('description'),
            'is_system' => filter_var($this->input('is_system', true), FILTER_VALIDATE_BOOLEAN),
            'active' => filter_var($this->input('active', true), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function rules(): array
    {
        $campaignStatusId = $this->route('campaign_status')?->id;

        return [
            'name' => ['required', 'string', 'max:191'],
            'slug' => [
                'required',
                'string',
                'max:191',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('campaign_statuses', 'slug')->ignore($campaignStatusId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'color_hex' => ['required', 'regex:/^#[0-9A-F]{6}$/'],
            'is_system' => ['boolean'],
            'active' => ['boolean'],
        ];
    }
}
