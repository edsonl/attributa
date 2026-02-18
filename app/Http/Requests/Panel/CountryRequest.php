<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'iso2'             => strtoupper((string) $this->input('iso2')),
            'iso3'             => strtoupper((string) $this->input('iso3')),
            'currency'         => strtoupper((string) $this->input('currency')),
            'currency_symbol'  => $this->input('currency_symbol'),
            'timezone_default' => $this->input('timezone_default'),
            'name'             => $this->input('name'),
            'active'           => filter_var($this->input('active', true), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function rules(): array
    {
        $countryId = $this->route('country')?->id;

        return [
            'iso2' => [
                'required',
                'string',
                'size:2',
                Rule::unique('countries', 'iso2')->ignore($countryId),
            ],
            'iso3' => [
                'required',
                'string',
                'size:3',
                Rule::unique('countries', 'iso3')->ignore($countryId),
            ],
            'name' => ['required', 'string', 'max:191'],
            'currency' => ['required', 'string', 'size:3'],
            'currency_symbol' => ['nullable', 'string', 'max:5'],
            'timezone_default' => ['required', 'string', 'max:191'],
            'active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'iso2.required' => 'Informe o codigo ISO2.',
            'iso2.size' => 'O codigo ISO2 deve ter exatamente 2 caracteres.',
            'iso2.unique' => 'Ja existe um pais com este codigo ISO2.',

            'iso3.required' => 'Informe o codigo ISO3.',
            'iso3.size' => 'O codigo ISO3 deve ter exatamente 3 caracteres.',
            'iso3.unique' => 'Ja existe um pais com este codigo ISO3.',

            'name.required' => 'Informe o nome do pais.',
            'name.max' => 'O nome pode ter no maximo 191 caracteres.',

            'currency.required' => 'Informe a moeda padrao do pais.',
            'currency.size' => 'O codigo da moeda deve ter exatamente 3 letras.',

            'currency_symbol.max' => 'O simbolo da moeda pode ter no maximo 5 caracteres.',

            'timezone_default.required' => 'Informe o timezone padrao.',
            'timezone_default.max' => 'O timezone pode ter no maximo 191 caracteres.',

            'active.boolean' => 'Selecione um valor valido para o status.',
        ];
    }
}
