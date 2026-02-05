<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskUpdateRequest extends FormRequest
{
    public function authorize(): bool { return auth()->check(); }


    public function rules(): array
    {
        return [
            'title' => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'status' => ['required', Rule::in(['pending','in_progress','done'])],
            'priority' => ['required', Rule::in(['low','medium','high'])],
            'due_date' => ['nullable','date'],
            'assigned_to_id' => ['nullable','integer','exists:users,id'],
            'company_id' => ['nullable','integer','exists:companies,id'],
        ];
    }
}
