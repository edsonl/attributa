<?php

namespace App\Services;

use App\Models\Browser;
use App\Models\DeviceCategory;
use DeviceDetector\DeviceDetector;

class DeviceClassificationService
{
    /** @var array<string,int> */
    protected array $deviceCategoryIdBySlug = [];

    /** @var array<string,int> */
    protected array $browserIdBySlug = [];

    /**
     * @return array<string,mixed>
     */
    public function classify(?string $userAgent): array
    {
        $ua = trim((string) $userAgent);

        if ($ua === '') {
            return $this->unknownPayload();
        }

        $detector = new DeviceDetector($ua);
        $detector->parse();

        if ($detector->isBot()) {
            return [
                'device_category_id' => $this->resolveDeviceCategoryId('bot'),
                'browser_id' => $this->resolveBrowserId('unknown'),
                'device_type' => 'bot',
                'device_brand' => null,
                'device_model' => null,
                'os_name' => null,
                'os_version' => null,
                'browser_name' => null,
                'browser_version' => null,
            ];
        }

        $deviceSlug = $this->mapDeviceSlug($detector->getDeviceName(), $detector->isDesktop());
        $client = $detector->getClient();
        $os = $detector->getOs();

        $browserName = is_array($client) ? $this->normalizeNullableString($client['name'] ?? null) : null;
        $browserVersion = is_array($client) ? $this->normalizeNullableString($client['version'] ?? null) : null;
        $browserSlug = $this->mapBrowserSlug($browserName);
        $osName = is_array($os) ? $this->normalizeNullableString($os['name'] ?? null) : null;
        $osVersion = is_array($os) ? $this->normalizeNullableString($os['version'] ?? null) : null;

        return [
            'device_category_id' => $this->resolveDeviceCategoryId($deviceSlug),
            'browser_id' => $this->resolveBrowserId($browserSlug),
            'device_type' => $deviceSlug,
            'device_brand' => $this->normalizeNullableString($detector->getBrandName()),
            'device_model' => $this->normalizeNullableString($detector->getModel()),
            'os_name' => $osName,
            'os_version' => $osVersion,
            'browser_name' => $browserName,
            'browser_version' => $browserVersion,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    protected function unknownPayload(): array
    {
        return [
            'device_category_id' => $this->resolveDeviceCategoryId('unknown'),
            'browser_id' => $this->resolveBrowserId('unknown'),
            'device_type' => 'unknown',
            'device_brand' => null,
            'device_model' => null,
            'os_name' => null,
            'os_version' => null,
            'browser_name' => null,
            'browser_version' => null,
        ];
    }

    protected function mapDeviceSlug(string $deviceName, bool $isDesktop): string
    {
        $name = strtolower(trim($deviceName));

        if ($name === 'desktop' || $isDesktop) {
            return 'desktop';
        }

        if (in_array($name, ['smartphone', 'feature phone', 'phablet', 'portable media player'], true)) {
            return 'mobile';
        }

        if ($name === 'tablet') {
            return 'tablet';
        }

        if (in_array($name, ['tv', 'smart display', 'smart speaker'], true)) {
            return 'smart_tv';
        }

        return 'unknown';
    }

    protected function mapBrowserSlug(?string $browserName): string
    {
        $name = strtolower(trim((string) $browserName));

        if ($name === '') {
            return 'unknown';
        }

        if (str_contains($name, 'samsung')) {
            return 'samsung_internet';
        }

        if (str_contains($name, 'chrome')) {
            return 'chrome';
        }

        if (str_contains($name, 'safari')) {
            return 'safari';
        }

        if (str_contains($name, 'firefox')) {
            return 'firefox';
        }

        if (str_contains($name, 'edge')) {
            return 'edge';
        }

        if (str_contains($name, 'opera')) {
            return 'opera';
        }

        return 'unknown';
    }

    protected function normalizeNullableString(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected function resolveDeviceCategoryId(string $slug): ?int
    {
        if ($this->deviceCategoryIdBySlug === []) {
            $this->deviceCategoryIdBySlug = DeviceCategory::query()
                ->pluck('id', 'slug')
                ->map(fn ($id) => (int) $id)
                ->toArray();
        }

        return $this->deviceCategoryIdBySlug[$slug]
            ?? $this->deviceCategoryIdBySlug['unknown']
            ?? null;
    }

    protected function resolveBrowserId(string $slug): ?int
    {
        if ($this->browserIdBySlug === []) {
            $this->browserIdBySlug = Browser::query()
                ->pluck('id', 'slug')
                ->map(fn ($id) => (int) $id)
                ->toArray();
        }

        return $this->browserIdBySlug[$slug]
            ?? $this->browserIdBySlug['unknown']
            ?? null;
    }
}
