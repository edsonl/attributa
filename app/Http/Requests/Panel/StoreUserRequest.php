<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user(); // ajuste conforme política de acesso
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:191'],
            'email'    => ['required', 'email', 'max:191', Rule::unique('users', 'email')],
            'notification_email' => ['nullable', 'email', 'max:191'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'notification_preferences' => ['nullable', 'array'],
            'notification_preferences.*.notification_type_id' => ['required', 'integer', Rule::exists('notification_types', 'id')],
            'notification_preferences.*.enabled_in_app' => ['nullable', 'boolean'],
            'notification_preferences.*.enabled_email' => ['nullable', 'boolean'],
            'notification_preferences.*.enabled_push' => ['nullable', 'boolean'],
            'notification_preferences.*.frequency' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => 'A confirmação de senha não confere.',
        ];
    }
}
