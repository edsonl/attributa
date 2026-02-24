<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\AffiliatePlatformRequest;
use App\Models\AffiliatePlatform;
use App\Services\HashidService;
use Illuminate\Http\Request;

class AffiliatePlatformController extends Controller
{
    public function index()
    {
        $userCode = app(HashidService::class)->encode((int) auth()->id());

        return inertia('Panel/AffiliatePlatforms/Index')
            ->with('title', 'Plataformas de Afiliado')
            ->with('integration', [
                'callback_base_url' => rtrim(config('app.url'), '/') . '/api/get/platform-lead',
                'user_code' => $userCode,
            ]);
    }

    public function data(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);
        $perPage = max(1, min($perPage, 100));
        $sortBy = $request->get('sortBy', 'name');
        $descending = filter_var($request->get('descending', false), FILTER_VALIDATE_BOOLEAN);
        $search = trim((string) $request->get('search', ''));

        $sortable = [
            'name' => 'name',
            'slug' => 'slug',
            'integration_type' => 'integration_type',
            'active' => 'active',
            'created_at' => 'created_at',
        ];

        $orderColumn = $sortable[$sortBy] ?? 'name';
        $orderDir = $descending ? 'desc' : 'asc';

        $query = AffiliatePlatform::query();
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $paginator = $query
            ->orderBy($orderColumn, $orderDir)
            ->paginate($perPage)
            ->appends($request->query())
            ->through(fn (AffiliatePlatform $affiliatePlatform) => $this->transform($affiliatePlatform));

        return response()->json($paginator);
    }

    public function store(AffiliatePlatformRequest $request)
    {
        $affiliatePlatform = AffiliatePlatform::query()->create($request->validated());

        return response()->json([
            'message' => 'Plataforma criada com sucesso.',
            'data' => $this->transform($affiliatePlatform),
        ], 201);
    }

    public function update(AffiliatePlatformRequest $request, AffiliatePlatform $affiliatePlatform)
    {
        $affiliatePlatform->update($request->validated());

        return response()->json([
            'message' => 'Plataforma atualizada com sucesso.',
            'data' => $this->transform($affiliatePlatform->fresh()),
        ]);
    }

    public function destroy(AffiliatePlatform $affiliatePlatform)
    {
        if ($affiliatePlatform->campaigns()->exists()) {
            return response()->json([
                'message' => 'Não é possível remover: existem campanhas vinculadas a esta plataforma.',
            ], 422);
        }

        $affiliatePlatform->delete();

        return response()->json([
            'message' => 'Plataforma removida com sucesso.',
        ]);
    }

    protected function transform(AffiliatePlatform $affiliatePlatform): array
    {
        $mapping = $affiliatePlatform->tracking_param_mapping ?: [];
        $rawLeadMapping = $affiliatePlatform->lead_param_mapping ?: [];
        $leadMapping = [
            'payout_amount' => trim((string) ($rawLeadMapping['payout_amount'] ?? '')),
            'currency_code' => trim((string) ($rawLeadMapping['currency_code'] ?? '')),
            'lead_status' => trim((string) ($rawLeadMapping['lead_status'] ?? '')),
            'platform_lead_id' => trim((string) ($rawLeadMapping['platform_lead_id'] ?? '')),
            'occurred_at' => trim((string) ($rawLeadMapping['occurred_at'] ?? '')),
            'offer_id' => trim((string) ($rawLeadMapping['offer_id'] ?? '')),
        ];
        $leadStatusMapping = [];
        foreach (($affiliatePlatform->lead_status_mapping ?: []) as $raw => $canonical) {
            $rawKey = strtolower(trim((string) $raw));
            $canonicalValue = strtolower(trim((string) $canonical));
            if ($rawKey === '' || $canonicalValue === '') {
                continue;
            }
            $leadStatusMapping[$rawKey] = $canonicalValue;
        }
        $additionalParams = $affiliatePlatform->postback_additional_params ?: [];
        $pairs = [];
        foreach ($mapping as $source => $target) {
            $pairs[] = $source . ' -> ' . $target;
        }

        $userCode = app(HashidService::class)->encode((int) auth()->id());
        $callbackUrl = $this->buildCallbackUrl($affiliatePlatform, $userCode);

        return [
            'id' => $affiliatePlatform->id,
            'name' => $affiliatePlatform->name,
            'slug' => $affiliatePlatform->slug,
            'active' => (bool) $affiliatePlatform->active,
            'active_label' => $affiliatePlatform->active ? 'Ativo' : 'Inativo',
            'integration_type' => $affiliatePlatform->integration_type,
            'integration_type_label' => $affiliatePlatform->integration_type === 'postback_get' ? 'Postback GET' : $affiliatePlatform->integration_type,
            'tracking_param_mapping' => $mapping,
            'lead_param_mapping' => $leadMapping,
            'lead_status_mapping' => $leadStatusMapping,
            'postback_additional_params' => $additionalParams,
            'mapping_preview' => empty($pairs) ? '-' : implode(', ', $pairs),
            'callback_url' => $callbackUrl,
            'created_at' => optional($affiliatePlatform->created_at)?->toIso8601String(),
            'updated_at' => optional($affiliatePlatform->updated_at)?->toIso8601String(),
        ];
    }

    protected function buildCallbackUrl(AffiliatePlatform $affiliatePlatform, string $userCode): string
    {
        $base = rtrim(config('app.url'), '/');
        $slug = trim((string) $affiliatePlatform->slug);
        $baseUrl = $base . '/api/get/platform-lead/' . rawurlencode($slug) . '/' . rawurlencode($userCode);

        $mapping = $affiliatePlatform->tracking_param_mapping ?: [];
        $trackingTargets = array_values(array_filter(array_map(
            fn ($value) => trim((string) $value),
            $mapping
        )));

        $additionalParams = array_values(array_filter(array_map(
            fn ($value) => trim((string) $value),
            $affiliatePlatform->postback_additional_params ?: []
        )));

        $rawLeadMapping = $affiliatePlatform->lead_param_mapping ?: [];
        $leadParams = array_values(array_filter([
            trim((string) ($rawLeadMapping['payout_amount'] ?? '')),
            trim((string) ($rawLeadMapping['currency_code'] ?? '')),
            trim((string) ($rawLeadMapping['lead_status'] ?? '')),
            trim((string) ($rawLeadMapping['platform_lead_id'] ?? '')),
            trim((string) ($rawLeadMapping['occurred_at'] ?? '')),
            trim((string) ($rawLeadMapping['offer_id'] ?? '')),
        ]));

        $params = array_values(array_unique(array_merge($trackingTargets, $leadParams, $additionalParams)));
        if (empty($params)) {
            return $baseUrl;
        }

        $queryParts = [];
        foreach ($params as $param) {
            $queryParts[] = rawurlencode($param) . '={' . $param . '}';
        }

        return $baseUrl . '?' . implode('&', $queryParts);
    }
}
