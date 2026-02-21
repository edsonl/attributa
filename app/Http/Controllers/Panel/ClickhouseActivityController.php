<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\ClickhouseDimensionCacheService;
use App\Services\ClickhouseHttpService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class ClickhouseActivityController extends Controller
{
    public function index()
    {
        return Inertia::render('Panel/ClickhouseActivity/Estatisticas');
    }

    public function campaigns()
    {
        return Campaign::query()
            ->where('user_id', (int) auth()->id())
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    public function data(
        Request $request,
        ClickhouseHttpService $clickhouse,
        ClickhouseDimensionCacheService $dimensionCache
    )
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $userId = (int) auth()->id();
        $perPage = (int) $request->get('per_page', 20);
        $perPage = min(max($perPage, 5), 50);
        $page = max(1, (int) $request->get('page', 1));
        $offset = ($page - 1) * $perPage;
        $sortBy = (string) $request->get('sortBy', 'created_at');
        $descending = filter_var($request->get('descending', true), FILTER_VALIDATE_BOOLEAN);

        $sortableColumns = [
            'created_at' => 'created_at',
            'campaign_name' => 'campaign_id',
            'traffic_source' => 'traffic_source_category_id',
            'device_browser' => 'device_type',
            'country_code' => 'country_code',
            'region_name' => 'region_name',
            'city' => 'city',
            'gclid' => 'gclid',
            'conversion' => 'conversion',
            'ip' => 'ip',
        ];

        $orderColumn = $sortableColumns[$sortBy] ?? 'created_at';
        $orderDir = $descending ? 'DESC' : 'ASC';

        $whereParts = ["user_id = {$userId}"];

        if ($request->filled('campaign_id')) {
            $campaignId = (int) $request->get('campaign_id');
            if ($campaignId > 0) {
                $whereParts[] = "campaign_id = {$campaignId}";
            }
        }

        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;
        $filterTimezone = 'America/Sao_Paulo';

        if ($dateFrom && $dateTo) {
            $startUtc = Carbon::createFromFormat('Y-m-d', $dateFrom, $filterTimezone)->startOfDay()->setTimezone('UTC');
            $endUtc = Carbon::createFromFormat('Y-m-d', $dateTo, $filterTimezone)->endOfDay()->setTimezone('UTC');
            $whereParts[] = "created_at >= '{$startUtc->format('Y-m-d H:i:s')}'";
            $whereParts[] = "created_at <= '{$endUtc->format('Y-m-d H:i:s')}'";
        } elseif ($dateFrom) {
            $startUtc = Carbon::createFromFormat('Y-m-d', $dateFrom, $filterTimezone)->startOfDay()->setTimezone('UTC');
            $whereParts[] = "created_at >= '{$startUtc->format('Y-m-d H:i:s')}'";
        } elseif ($dateTo) {
            $endUtc = Carbon::createFromFormat('Y-m-d', $dateTo, $filterTimezone)->endOfDay()->setTimezone('UTC');
            $whereParts[] = "created_at <= '{$endUtc->format('Y-m-d H:i:s')}'";
        }

        $whereSql = implode(' AND ', $whereParts);
        $db = $clickhouse->quoteIdentifier($clickhouse->databaseName());
        $table = $clickhouse->quoteIdentifier('pageviews');

        $countSql = <<<SQL
SELECT count() AS total
FROM {$db}.{$table}
WHERE {$whereSql}
FORMAT JSON
SQL;

        $listSql = sprintf("                        SELECT
                            id,
                            created_at,
                            ip,
                            country_code,
                            region_name,
                            city,
                            conversion,
                            campaign_id,
                            traffic_source_category_id,
                            ip_category_id,
                            device_category_id,
                            browser_id,
                            device_type,
                            browser_name,
                            if(gclid IS NOT NULL AND gclid != '', 1, 0) AS has_gclid
                        FROM %s.%s
                        WHERE %s
                        ORDER BY %s %s
                        LIMIT %s
                        OFFSET %s
                        FORMAT JSON", $db, $table, $whereSql, $orderColumn, $orderDir, $perPage, $offset);

        $countBody = json_decode($clickhouse->execute($countSql), true);
        $listBody = json_decode($clickhouse->execute($listSql), true);

        $total = (int) (($countBody['data'][0]['total'] ?? 0));
        $rows = $listBody['data'] ?? [];
        $tz = 'America/Sao_Paulo';
        $campaignMap = $dimensionCache->campaignMapForUser($userId);
        $trafficSourceMap = $dimensionCache->trafficSourceMap();
        $trafficSourceMetaById = $dimensionCache->trafficSourceMetaById();
        $ipCategoryMap = $dimensionCache->ipCategoryMap();
        $ipCategoryMetaById = $dimensionCache->ipCategoryMetaById();
        $deviceMetaById = $dimensionCache->deviceMetaById();
        $deviceMetaBySlug = $dimensionCache->deviceMetaBySlug();
        $browserMetaById = $dimensionCache->browserMetaById();
        $browserMetaByName = $dimensionCache->browserMetaByName();

        $rows = array_map(function (array $row) use (
            $tz,
            $campaignMap,
            $trafficSourceMap,
            $trafficSourceMetaById,
            $ipCategoryMap,
            $ipCategoryMetaById,
            $deviceMetaById,
            $deviceMetaBySlug,
            $browserMetaById,
            $browserMetaByName
        ) {
            $createdAt = $row['created_at'] ?? null;
            $row['created_at_formatted'] = $createdAt
                ? Carbon::parse($createdAt, 'UTC')->setTimezone($tz)->format('d/m/Y, H:i:s')
                : null;

            $campaignId = $row['campaign_id'] ?? null;
            $row['campaign_name'] = $this->resolveDimensionLabel($campaignId, $campaignMap);

            $trafficId = $row['traffic_source_category_id'] ?? null;
            $row['traffic_source_name'] = $this->resolveDimensionLabel($trafficId, $trafficSourceMap);
            $trafficMeta = $this->resolveMetaById($trafficId, $trafficSourceMetaById);
            $row['traffic_source_icon'] = $trafficMeta['icon_name'] ?? 'help_outline';
            $row['traffic_source_color'] = $trafficMeta['color_hex'] ?? '#64748B';

            $ipCategoryId = $row['ip_category_id'] ?? null;
            $row['ip_category_name'] = $this->resolveDimensionLabel($ipCategoryId, $ipCategoryMap);
            $ipMeta = $this->resolveMetaById($ipCategoryId, $ipCategoryMetaById);
            $row['ip_category_color'] = $ipMeta['color_hex'] ?? '#FCE7F3';
            $row['ip_category_description'] = $ipMeta['description'] ?? 'Categoria ainda não determinada.';

            $deviceCategoryId = $row['device_category_id'] ?? null;
            $deviceType = strtolower(trim((string) ($row['device_type'] ?? '')));
            $deviceMeta = $this->resolveMetaById($deviceCategoryId, $deviceMetaById);
            if ($deviceMeta === null && $deviceType !== '' && array_key_exists($deviceType, $deviceMetaBySlug)) {
                $deviceMeta = $deviceMetaBySlug[$deviceType];
            }
            $row['device_icon'] = $deviceMeta['icon_name'] ?? 'devices_other';
            $row['device_color'] = $deviceMeta['color_hex'] ?? '#64748B';

            $browserId = $row['browser_id'] ?? null;
            $browserName = strtolower(trim((string) ($row['browser_name'] ?? '')));
            $browserMeta = $this->resolveMetaById($browserId, $browserMetaById);
            if ($browserMeta === null && $browserName !== '' && array_key_exists($browserName, $browserMetaByName)) {
                $browserMeta = $browserMetaByName[$browserName];
            }
            $row['browser_icon'] = $browserMeta['icon_name'] ?? null;
            $row['browser_color'] = $browserMeta['color_hex'] ?? '#64748B';

            return $row;
        }, $rows);

        return response()->json([
            'data' => array_values($rows),
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
            'from' => $total > 0 ? $offset + 1 : null,
            'to' => min($offset + $perPage, $total),
        ]);
    }

    private function resolveDimensionLabel(mixed $id, array $map): string
    {
        if ($id === null || $id === '') {
            return '-';
        }

        $raw = (string) $id;
        if (array_key_exists($raw, $map)) {
            return (string) $map[$raw];
        }

        $intId = (int) $id;
        if ($intId > 0 && array_key_exists($intId, $map)) {
            return (string) $map[$intId];
        }

        return 'ID ' . $raw;
    }

    private function resolveMetaById(mixed $id, array $map): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }

        $raw = (string) $id;
        if (array_key_exists($raw, $map) && is_array($map[$raw])) {
            return $map[$raw];
        }

        $intId = (int) $id;
        if ($intId > 0 && array_key_exists($intId, $map) && is_array($map[$intId])) {
            return $map[$intId];
        }

        return null;
    }
}
