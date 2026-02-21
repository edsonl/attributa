<?php

namespace App\Jobs;

use App\Models\Pageview;
use App\Services\ClickhousePageviewUpdater;
use App\Services\DeviceClassificationService;
use App\Services\IpClassifierService;
use Illuminate\Bus\Queueable;
//use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIpClassificationJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120; // tempo máximo do job

    public function handle(): void
    {
        $classifier = app(IpClassifierService::class);
        $deviceClassifier = app(DeviceClassificationService::class);
        $clickhouseUpdater = app(ClickhousePageviewUpdater::class);
        $clickhouseActive = (bool) config('clickhouse.active', false);

        Pageview::whereNull('ip_category_id')
            ->limit(50)
            ->get()
            ->each(function ($pageview) use ($classifier, $deviceClassifier, $clickhouseUpdater, $clickhouseActive) {

                $result = $classifier->classify(
                    $pageview->ip,
                    $pageview->user_agent ?? null
                );
                $device = $deviceClassifier->classify($pageview->user_agent);

                $updatePayload = [
                    'ip_category_id' => $result['ip_category_id'],
                    'country_code' => $result['geo']['country_code'] ?? null,
                    'country_name' => $result['geo']['country_name'] ?? null,
                    'region_name' => $result['geo']['region_name'] ?? null,
                    'city' => $result['geo']['city'] ?? null,
                    'latitude' => $result['geo']['latitude'] ?? null,
                    'longitude' => $result['geo']['longitude'] ?? null,
                    'timezone' => $result['geo']['timezone'] ?? null,
                    'device_category_id' => $device['device_category_id'] ?? null,
                    'browser_id' => $device['browser_id'] ?? null,
                    'device_type' => $device['device_type'] ?? null,
                    'device_brand' => $device['device_brand'] ?? null,
                    'device_model' => $device['device_model'] ?? null,
                    'os_name' => $device['os_name'] ?? null,
                    'os_version' => $device['os_version'] ?? null,
                    'browser_name' => $device['browser_name'] ?? null,
                    'browser_version' => $device['browser_version'] ?? null,
                ];

                $pageview->update($updatePayload);

                if ($clickhouseActive) {
                    try {
                        $clickhouseUpdater->updateById((int) $pageview->id, $updatePayload);
                    } catch (\Throwable $e) {
                        Log::channel('tracking_collect')->warning(
                            'Falha ao atualizar enriquecimento da pageview no ClickHouse.',
                            [
                                'pageview_id' => (int) $pageview->id,
                                'error' => $e->getMessage(),
                            ]
                        );
                    }
                }
            });
    }

}
