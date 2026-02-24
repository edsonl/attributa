<?php

namespace App\Http\Requests\Panel;

use App\Models\Lead;
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

        $rawLeadMapping = $this->input('lead_param_mapping', []);
        if (!is_array($rawLeadMapping)) {
            $rawLeadMapping = [];
        }

        $leadMapping = [];
        $acceptedKeys = ['payout_amount', 'currency_code', 'lead_status', 'platform_lead_id', 'occurred_at', 'offer_id'];
        foreach ($acceptedKeys as $key) {
            $param = trim((string) ($rawLeadMapping[$key] ?? ''));
            if ($param === '') {
                continue;
            }
            $leadMapping[$key] = $param;
        }

        $rawLeadStatusMapping = $this->input('lead_status_mapping', []);
        if (!is_array($rawLeadStatusMapping)) {
            $rawLeadStatusMapping = [];
        }

        $leadStatusMapping = [];
        foreach ($rawLeadStatusMapping as $rawStatus => $canonicalStatus) {
            $rawKey = strtolower(trim((string) $rawStatus));
            $canonical = strtolower(trim((string) $canonicalStatus));
            if ($rawKey === '' || $canonical === '') {
                continue;
            }
            $leadStatusMapping[$rawKey] = $canonical;
        }

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'slug' => strtolower(trim((string) $this->input('slug'))),
            'active' => filter_var($this->input('active', true), FILTER_VALIDATE_BOOLEAN),
            'integration_type' => strtolower(trim((string) $this->input('integration_type', 'postback_get'))),
            'tracking_param_mapping' => $mapping,
            'lead_param_mapping' => $leadMapping,
            'lead_status_mapping' => $leadStatusMapping,
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
            'lead_param_mapping' => ['nullable', 'array'],
            'lead_param_mapping.payout_amount' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'lead_param_mapping.currency_code' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'lead_param_mapping.lead_status' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'lead_param_mapping.platform_lead_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'lead_param_mapping.occurred_at' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'lead_param_mapping.offer_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'lead_status_mapping' => ['nullable', 'array'],
            'lead_status_mapping.*' => ['nullable', 'string', Rule::in(Lead::ALLOWED_STATUSES)],
            'postback_additional_params' => ['nullable', 'array'],
            'postback_additional_params.*' => ['string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/'],
        ];
    }
}
