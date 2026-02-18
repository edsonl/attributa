<?php
namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskStoreRequest extends FormRequest
{
    public function authorize(): bool { return auth()->check(); }


    public function rules(): array
    {
        return [
            'title' => ['required','string','max:191'],
            'description' => ['nullable','string'],
            'status' => ['required', Rule::in(['pending','in_progress','done'])],
            'priority' => ['required', Rule::in(['low','medium','high'])],
            'due_date' => ['nullable','date'],
            'company_id' => ['nullable','integer','exists:companies,id'],
            'assigned_to_id' => ['nullable','integer','exists:users,id'],
        ];
    }
}
