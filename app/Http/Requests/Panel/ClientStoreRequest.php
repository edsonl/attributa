<?php


namespace App\Http\Requests\Panel;


use Illuminate\Foundation\Http\FormRequest;


class ClientStoreRequest extends FormRequest
{
    public function authorize(): bool { return auth()->check(); }

    public function rules(): array
    {
        return [
            'name' => ['required','string','max:255'],
            'website' => ['nullable','url','max:255'],
            'order' => ['required','integer','min:0','max:999999'],
            'visible' => ['required','boolean'],
            'image' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
            // metadados de redimensionamento
            'image_max_width' => ['nullable','integer','min:1','max:4000'],
            'image_max_height' => ['nullable','integer','min:1','max:4000'],
        ];
    }
}
