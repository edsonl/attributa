<?php

namespace App\Services;

use App\Models\Pageview;

class ClickhousePageviewWriter
{
    public function __construct(
        protected ClickhouseHttpService $clickhouse
    ) {
    }

    public function insert(Pageview $pageview): void
    {
        $db = $this->clickhouse->quoteIdentifier($this->clickhouse->databaseName());
        $table = $this->clickhouse->quoteIdentifier('pageviews');

        $row = [
            'id' => (int) $pageview->id,
            'user_id' => (int) $pageview->user_id,
            'campaign_id' => $pageview->campaign_id !== null ? (int) $pageview->campaign_id : null,
            'traffic_source_category_id' => $pageview->traffic_source_category_id !== null ? (int) $pageview->traffic_source_category_id : null,
            'device_category_id' => $pageview->device_category_id !== null ? (int) $pageview->device_category_id : null,
            'browser_id' => $pageview->browser_id !== null ? (int) $pageview->browser_id : null,
            'ip_category_id' => $pageview->ip_category_id !== null ? (int) $pageview->ip_category_id : null,
            'url' => (string) $pageview->url,
            'landing_url' => $pageview->landing_url,
            'referrer' => $pageview->referrer,
            'user_agent' => $pageview->user_agent,
            'utm_source' => $pageview->utm_source,
            'utm_medium' => $pageview->utm_medium,
            'utm_campaign' => $pageview->utm_campaign,
            'utm_term' => $pageview->utm_term,
            'utm_content' => $pageview->utm_content,
            'gclid' => $pageview->gclid,
            'gad_campaignid' => $pageview->gad_campaignid,
            'fbclid' => $pageview->fbclid,
            'ttclid' => $pageview->ttclid,
            'msclkid' => $pageview->msclkid,
            'wbraid' => $pageview->wbraid,
            'gbraid' => $pageview->gbraid,
            'traffic_source_reason' => $pageview->traffic_source_reason,
            'device_type' => $pageview->device_type,
            'device_brand' => $pageview->device_brand,
            'device_model' => $pageview->device_model,
            'os_name' => $pageview->os_name,
            'os_version' => $pageview->os_version,
            'browser_name' => $pageview->browser_name,
            'browser_version' => $pageview->browser_version,
            'screen_width' => $pageview->screen_width !== null ? (int) $pageview->screen_width : null,
            'screen_height' => $pageview->screen_height !== null ? (int) $pageview->screen_height : null,
            'viewport_width' => $pageview->viewport_width !== null ? (int) $pageview->viewport_width : null,
            'viewport_height' => $pageview->viewport_height !== null ? (int) $pageview->viewport_height : null,
            'device_pixel_ratio' => $pageview->device_pixel_ratio !== null ? number_format((float) $pageview->device_pixel_ratio, 2, '.', '') : null,
            'platform' => $pageview->platform,
            'language' => $pageview->language,
            'ip' => $pageview->ip,
            'country_code' => $pageview->country_code,
            'country_name' => $pageview->country_name,
            'region_name' => $pageview->region_name,
            'city' => $pageview->city,
            'latitude' => $pageview->latitude !== null ? number_format((float) $pageview->latitude, 7, '.', '') : null,
            'longitude' => $pageview->longitude !== null ? number_format((float) $pageview->longitude, 7, '.', '') : null,
            'timezone' => $pageview->timezone,
            'timestamp_ms' => $pageview->timestamp_ms !== null ? (int) $pageview->timestamp_ms : null,
            'conversion' => $pageview->conversion ? 1 : 0,
            'created_at' => $pageview->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $pageview->updated_at?->format('Y-m-d H:i:s'),
        ];

        $jsonRow = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $sql = "INSERT INTO {$db}.{$table} FORMAT JSONEachRow\n{$jsonRow}";

        $this->clickhouse->execute($sql);
    }
}

