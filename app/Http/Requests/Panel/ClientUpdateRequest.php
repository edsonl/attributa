<?php


namespace App\Http\Requests\Panel;


use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;


class ClientUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        Log::info('ClientUpdateRequest::prepareForValidation', [
            'all' => $this->all(),
            'has_image' => $this->hasFile('image'),
            'image_name' => $this->file('image')?->getClientOriginalName(),
        ]);
    }

    protected function passedValidation(): void
    {
        Log::info('ClientUpdateRequest::passedValidation', $this->validated());
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                Log::warning('ClientUpdateRequest::errors', $v->errors()->toArray());
            }
        });
    }

    protected function failedValidation($validator)
    {
        Log::error('ClientUpdateRequest::failedValidation', $validator->errors()->toArray());
        throw (new ValidationException($validator));
    }

    public function rules(): array
    {
        return [
            'name' => ['required','string','max:191'],
            'website' => ['nullable','url','max:255'],
            'order' => ['required','integer','min:0','max:999999'],
            'visible' => ['required','boolean'],
            'image' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
            'remove_image' => ['nullable','boolean'],
            'image_max_width' => ['nullable','integer','min:1','max:4000'],
            'image_max_height' => ['nullable','integer','min:1','max:4000'],
        ];
    }
}
