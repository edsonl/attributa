<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

class CompanyUpdateRequest extends FormRequest
{
    public function authorize(): bool { return auth()->check(); }

    public function rules(): array
    {
        return [
            'name'          => ['required','string','max:255'],
            'corporate_name'=> ['nullable','string','max:255'],
            'phone'         => ['nullable','string','max:30'],
            'whatsapp'      => ['nullable','string','max:30'],
            'email'         => ['nullable','email','max:255'],
            'site'          => ['nullable','url','max:255'],
            'notes'         => ['nullable','string','max:255'],
            'cnpj'          => ['nullable','string','max:20'],
            'company_id'    => ['nullable','integer','exists:companies,id'],
        ];
    }
}
