<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Ajuste futuramente se houver política (Policy) para campanhas
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Baseado diretamente nas migrations:
     * - campaigns
     * - channels
     * - countries
     * - campaign_country
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'pixel_code'=>['string','max:60','nullable'],

            'status' => [
                'required',
                'boolean',
            ],

            'channel_id' => [
                'required',
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
                'exists:google_ads_accounts,id',
            ],

        ];
    }

    /**
     * Custom validation messages (opcional, mas já deixamos pronto)
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome da campanha é obrigatório.',
            'name.max' => 'O nome da campanha não pode ter mais de 255 caracteres.',

            'piexl_code.max' => 'O pixel não pode ter mais de 60 caracteres.',

            'status.required' => 'O status da campanha é obrigatório.',
            'status.boolean' => 'O status da campanha é inválido.',

            'channel_id.required' => 'O canal é obrigatório.',
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
     * Garante coerência caso venha string "true"/"false" do frontend.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('status')) {
            $this->merge([
                'status' => filter_var($this->status, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
