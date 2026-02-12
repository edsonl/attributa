<?php

namespace App\Jobs;

use App\Models\Pageview;
use App\Services\IpClassifierService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIpClassificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120; // tempo máximo do job

    public function handle(IpClassifierService $classifier): void
    {
        // Processar em lotes para não sobrecarregar
        Pageview::whereNull('ip_category_id')
            ->limit(50)
            ->get()
            ->each(function ($pageview) use ($classifier) {

                try {

                    $result = $classifier->classify(
                        $pageview->ip,
                        $pageview->user_agent ?? null
                    );

                    $pageview->update([
                        'ip_category_id' => $result['ip_category_id'],
                        'country_code' => $result['geo']['country_code'] ?? null,
                        'country_name' => $result['geo']['country_name'] ?? null,
                        'region_name' => $result['geo']['region_name'] ?? null,
                        'city' => $result['geo']['city'] ?? null,
                        'latitude' => $result['geo']['latitude'] ?? null,
                        'longitude' => $result['geo']['longitude'] ?? null,
                        'timezone' => $result['geo']['timezone'] ?? null,
                    ]);

                } catch (\Throwable $e) {

                    Log::error('IP classification failed', [
                        'pageview_id' => $pageview->id,
                        'ip' => $pageview->ip,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }
}
