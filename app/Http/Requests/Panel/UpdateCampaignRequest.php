<?php

namespace App\Http\Requests\Panel;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCampaignRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Ajuste futuramente se houver Policy de Campaign
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Mesmas regras do Store, pois todos os campos
     * continuam obrigatórios na edição.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:74',
            ],

            'product_url' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (Campaign::normalizeProductUrl((string) $value) === null) {
                        $fail('Informe uma URL válida com http:// ou https://.');
                    }
                },
            ],

            'conversion_goal_id' => [
                'nullable',
                'integer',
                Rule::exists('conversion_goals', 'id')
                    ->where(fn ($query) => $query->where('user_id', (int) $this->user()?->id)),
            ],

            'campaign_status_id' => [
                'required',
                'integer',
                'exists:campaign_statuses,id',
            ],

            'channel_id' => [
                'nullable',
                'integer',
                'exists:channels,id',
            ],

           'affiliate_platform_id' => [
                'required',
                'integer',
                'exists:affiliate_platforms,id',
            ],

            'countries' => [
                'nullable',
                'array',
            ],

            'countries.*' => [
                'integer',
                'exists:countries,id',
            ],

            'commission_value' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'google_ads_account_id' => [
                'nullable',
                Rule::exists('google_ads_accounts', 'id')
                    ->where(fn ($query) => $query->where('user_id', (int) $this->user()?->id)),
            ],

        ];
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome da campanha é obrigatório.',
            'name.max' => 'O nome da campanha não pode ter mais de 74 caracteres.',
            'product_url.required' => 'A URL do produto é obrigatória.',
            'product_url.max' => 'A URL do produto não pode ter mais de 255 caracteres.',

            'conversion_goal_id.exists' => 'A meta de conversão selecionada é inválida.',

            'campaign_status_id.required' => 'O status da campanha é obrigatório.',
            'campaign_status_id.exists' => 'O status da campanha é inválido.',

            'channel_id.exists' => 'O canal selecionado é inválido.',

            'affiliate_platform_id.required' => 'A plataforma é obrigatória.',
            'affiliate_platform_id.exists' => 'O plataforma selecionada é inválida.',

            'countries.array' => 'O formato dos países é inválido.',
            'countries.*.exists' => 'Um ou mais países selecionados são inválidos.',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Normaliza o campo status caso venha como string do frontend.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('campaign_status_id')) {
            $this->merge([
                'campaign_status_id' => (int) $this->input('campaign_status_id'),
            ]);
        }

        if ($this->has('product_url')) {
            $this->merge([
                'product_url' => trim((string) $this->input('product_url')),
            ]);
        }
    }
}
