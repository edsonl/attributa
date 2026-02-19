<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AffiliatePlatformRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        $rawMapping = $this->input('tracking_param_mapping', []);
        if (!is_array($rawMapping)) {
            $rawMapping = [];
        }

        $mapping = [];
        foreach ($rawMapping as $source => $target) {
            $sourceKey = trim((string) $source);
            $targetKey = trim((string) $target);
            if ($sourceKey === '' || $targetKey === '') {
                continue;
            }

            $mapping[$sourceKey] = $targetKey;
        }

        $rawAdditionalParams = $this->input('postback_additional_params', []);
        if (!is_array($rawAdditionalParams)) {
            $rawAdditionalParams = [];
        }

        $additionalParams = [];
        foreach ($rawAdditionalParams as $paramName) {
            $param = trim((string) $paramName);
            if ($param === '') {
                continue;
            }
            $additionalParams[] = $param;
        }

        $additionalParams = array_values(array_unique($additionalParams));

        $rawConversionMapping = $this->input('conversion_param_mapping', []);
        if (!is_array($rawConversionMapping)) {
            $rawConversionMapping = [];
        }

        $conversionMapping = [];
        foreach (['conversion_value', 'currency_code'] as $key) {
            $param = trim((string) ($rawConversionMapping[$key] ?? ''));
            if ($param === '') {
                continue;
            }
            $conversionMapping[$key] = $param;
        }

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'slug' => strtolower(trim((string) $this->input('slug'))),
            'active' => filter_var($this->input('active', true), FILTER_VALIDATE_BOOLEAN),
            'integration_type' => strtolower(trim((string) $this->input('integration_type', 'postback_get'))),
            'tracking_param_mapping' => $mapping,
            'conversion_param_mapping' => $conversionMapping,
            'postback_additional_params' => $additionalParams,
        ]);
    }

    public function rules(): array
    {
        $affiliatePlatformId = $this->route('affiliate_platform')?->id;

        return [
            'name' => ['required', 'string', 'max:191'],
            'slug' => [
                'required',
                'string',
                'max:191',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('affiliate_platforms', 'slug')->ignore($affiliatePlatformId),
            ],
            'active' => ['boolean'],
            'integration_type' => ['required', 'string', Rule::in(['postback_get'])],
            'tracking_param_mapping' => ['nullable', 'array'],
            'conversion_param_mapping' => ['nullable', 'array'],
            'conversion_param_mapping.conversion_value' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'conversion_param_mapping.currency_code' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'postback_additional_params' => ['nullable', 'array'],
            'postback_additional_params.*' => ['string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/'],
        ];
    }
}
