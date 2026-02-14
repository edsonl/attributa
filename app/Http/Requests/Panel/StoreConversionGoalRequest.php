<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversionGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'goal_code' => [
                'required',
                'string',
                'max:60',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('conversion_goals', 'goal_code')
                    ->where(fn ($query) => $query->where('user_id', (int) $this->user()?->id)),
            ],
            'timezone_id' => [
                'required',
                'integer',
                'exists:timezones,id',
            ],
            'active' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'goal_code.required' => 'O código da meta de conversão é obrigatório.',
            'goal_code.max' => 'O código da meta de conversão não pode ter mais de 60 caracteres.',
            'goal_code.regex' => 'Use apenas letras, números, hífen e underscore (sem espaços, acentos ou caracteres especiais).',
            'goal_code.unique' => 'Este código já está cadastrado para o seu usuário.',
            'timezone_id.required' => 'O timezone da meta de conversão é obrigatório.',
            'timezone_id.integer' => 'O timezone da meta de conversão é inválido.',
            'timezone_id.exists' => 'O timezone selecionado não existe.',
            'active.required' => 'O status da meta de conversão é obrigatório.',
            'active.boolean' => 'O status da meta de conversão é inválido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('goal_code')) {
            $this->merge([
                'goal_code' => trim((string) $this->input('goal_code')),
            ]);
        }

        if ($this->has('active')) {
            $this->merge([
                'active' => filter_var($this->input('active'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->has('timezone_id')) {
            $this->merge([
                'timezone_id' => (int) $this->input('timezone_id'),
            ]);
        }
    }
}
