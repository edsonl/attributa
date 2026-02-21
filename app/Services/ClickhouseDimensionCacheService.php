<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Browser;
use App\Models\DeviceCategory;
use App\Models\IpCategory;
use App\Models\TrafficSourceCategory;
use Illuminate\Support\Facades\Cache;

class ClickhouseDimensionCacheService
{
    private const TTL_SECONDS = 86400;

    public function campaignMapForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return Cache::remember(
            $this->campaignCacheKey($userId),
            self::TTL_SECONDS,
            fn () => $this->buildCampaignMapForUser($userId)
        );
    }

    public function refreshCampaignMapForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $map = $this->buildCampaignMapForUser($userId);
        Cache::put($this->campaignCacheKey($userId), $map, self::TTL_SECONDS);

        return $map;
    }

    public function trafficSourceMap(): array
    {
        return Cache::remember(
            'clickhouse:dim:traffic-sources:map',
            self::TTL_SECONDS,
            fn () => TrafficSourceCategory::query()
                ->orderBy('id')
                ->pluck('name', 'id')
                ->all()
        );
    }

    public function trafficSourceMetaById(): array
    {
        return Cache::remember(
            'clickhouse:dim:traffic-sources:meta-by-id',
            self::TTL_SECONDS,
            fn () => TrafficSourceCategory::query()
                ->orderBy('id')
                ->get(['id', 'name', 'icon_name', 'color_hex'])
                ->mapWithKeys(fn (TrafficSourceCategory $item) => [
                    (int) $item->id => [
                        'name' => (string) $item->name,
                        'icon_name' => $item->icon_name ? (string) $item->icon_name : null,
                        'color_hex' => $item->color_hex ? (string) $item->color_hex : null,
                    ],
                ])
                ->all()
        );
    }

    public function ipCategoryMap(): array
    {
        return Cache::remember(
            'clickhouse:dim:ip-categories:map',
            self::TTL_SECONDS,
            fn () => IpCategory::query()
                ->orderBy('id')
                ->pluck('name', 'id')
                ->all()
        );
    }

    public function ipCategoryMetaById(): array
    {
        return Cache::remember(
            'clickhouse:dim:ip-categories:meta-by-id',
            self::TTL_SECONDS,
            fn () => IpCategory::query()
                ->orderBy('id')
                ->get(['id', 'name', 'color_hex', 'description'])
                ->mapWithKeys(fn (IpCategory $item) => [
                    (int) $item->id => [
                        'name' => (string) $item->name,
                        'color_hex' => $item->color_hex ? (string) $item->color_hex : null,
                        'description' => $item->description ? (string) $item->description : null,
                    ],
                ])
                ->all()
        );
    }

    public function deviceMetaById(): array
    {
        return Cache::remember(
            'clickhouse:dim:device-categories:meta-by-id',
            self::TTL_SECONDS,
            fn () => DeviceCategory::query()
                ->orderBy('id')
                ->get(['id', 'name', 'slug', 'icon_name', 'color_hex'])
                ->mapWithKeys(fn (DeviceCategory $item) => [
                    (int) $item->id => [
                        'name' => (string) $item->name,
                        'slug' => $item->slug ? (string) $item->slug : null,
                        'icon_name' => $item->icon_name ? (string) $item->icon_name : null,
                        'color_hex' => $item->color_hex ? (string) $item->color_hex : null,
                    ],
                ])
                ->all()
        );
    }

    public function deviceMetaBySlug(): array
    {
        return Cache::remember(
            'clickhouse:dim:device-categories:meta-by-slug',
            self::TTL_SECONDS,
            fn () => DeviceCategory::query()
                ->whereNotNull('slug')
                ->orderBy('id')
                ->get(['name', 'slug', 'icon_name', 'color_hex'])
                ->mapWithKeys(fn (DeviceCategory $item) => [
                    strtolower((string) $item->slug) => [
                        'name' => (string) $item->name,
                        'slug' => (string) $item->slug,
                        'icon_name' => $item->icon_name ? (string) $item->icon_name : null,
                        'color_hex' => $item->color_hex ? (string) $item->color_hex : null,
                    ],
                ])
                ->all()
        );
    }

    public function browserMetaById(): array
    {
        return Cache::remember(
            'clickhouse:dim:browsers:meta-by-id',
            self::TTL_SECONDS,
            fn () => Browser::query()
                ->orderBy('id')
                ->get(['id', 'name', 'slug', 'icon_name', 'color_hex'])
                ->mapWithKeys(fn (Browser $item) => [
                    (int) $item->id => [
                        'name' => (string) $item->name,
                        'slug' => $item->slug ? (string) $item->slug : null,
                        'icon_name' => $item->icon_name ? (string) $item->icon_name : null,
                        'color_hex' => $item->color_hex ? (string) $item->color_hex : null,
                    ],
                ])
                ->all()
        );
    }

    public function browserMetaByName(): array
    {
        return Cache::remember(
            'clickhouse:dim:browsers:meta-by-name',
            self::TTL_SECONDS,
            fn () => Browser::query()
                ->whereNotNull('name')
                ->orderBy('id')
                ->get(['name', 'slug', 'icon_name', 'color_hex'])
                ->mapWithKeys(fn (Browser $item) => [
                    strtolower((string) $item->name) => [
                        'name' => (string) $item->name,
                        'slug' => $item->slug ? (string) $item->slug : null,
                        'icon_name' => $item->icon_name ? (string) $item->icon_name : null,
                        'color_hex' => $item->color_hex ? (string) $item->color_hex : null,
                    ],
                ])
                ->all()
        );
    }

    private function buildCampaignMapForUser(int $userId): array
    {
        return Campaign::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->pluck('name', 'id')
            ->all();
    }

    private function campaignCacheKey(int $userId): string
    {
        return 'clickhouse:dim:campaigns:user:' . $userId . ':map';
    }
}
