<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualConversionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $gclid = trim((string) $this->input('gclid', ''));
        if ($gclid !== '') {
            $gclid = mb_substr($gclid, 0, 150);
        }

        $this->merge([
            'campaign_id' => $this->input('campaign_id'),
            'conversion_event_time' => $this->input('conversion_event_time'),
            'conversion_timezone' => trim((string) $this->input('conversion_timezone', '')),
            'conversion_value' => $this->input('conversion_value', 1),
            'gclid' => $gclid === '' ? null : $gclid,
        ]);
    }

    public function rules(): array
    {
        $userId = (int) auth()->id();

        return [
            'campaign_id' => [
                'required',
                'integer',
                Rule::exists('campaigns', 'id')->where(
                    fn ($query) => $query->where('user_id', $userId)
                ),
            ],
            'conversion_event_time' => ['required', 'date'],
            'conversion_timezone' => ['required', 'string', Rule::exists('timezones', 'identifier')],
            'conversion_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'gclid' => ['required', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'campaign_id.required' => 'Selecione a campanha.',
            'campaign_id.integer' => 'A campanha selecionada é inválida.',
            'campaign_id.exists' => 'A campanha selecionada é inválida.',

            'conversion_event_time.required' => 'Informe a data/hora da conversão.',
            'conversion_event_time.date' => 'A data/hora da conversão é inválida.',
            'conversion_timezone.required' => 'Selecione o timezone da conversão.',
            'conversion_timezone.exists' => 'O timezone selecionado é inválido.',

            'conversion_value.numeric' => 'O valor deve ser numérico.',
            'conversion_value.min' => 'O valor não pode ser negativo.',
            'conversion_value.max' => 'O valor é maior que o limite permitido.',

            'gclid.required' => 'Informe o GCLID.',
            'gclid.string' => 'O GCLID informado é inválido.',
            'gclid.max' => 'O GCLID pode ter no máximo 150 caracteres.',
        ];
    }
}
