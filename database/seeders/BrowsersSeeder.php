<?php

namespace Database\Seeders;

use App\Models\Browser;
use Illuminate\Database\Seeder;

class BrowsersSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'name' => 'Chrome',
                'slug' => 'chrome',
                'icon_name' => 'public',
                'color_hex' => '#2563EB',
                'description' => 'Google Chrome.',
                'is_system' => true,
            ],
            [
                'name' => 'Safari',
                'slug' => 'safari',
                'icon_name' => 'travel_explore',
                'color_hex' => '#0891B2',
                'description' => 'Safari.',
                'is_system' => true,
            ],
            [
                'name' => 'Firefox',
                'slug' => 'firefox',
                'icon_name' => 'local_fire_department',
                'color_hex' => '#EA580C',
                'description' => 'Mozilla Firefox.',
                'is_system' => true,
            ],
            [
                'name' => 'Edge',
                'slug' => 'edge',
                'icon_name' => 'change_history',
                'color_hex' => '#0EA5E9',
                'description' => 'Microsoft Edge.',
                'is_system' => true,
            ],
            [
                'name' => 'Opera',
                'slug' => 'opera',
                'icon_name' => 'donut_large',
                'color_hex' => '#DC2626',
                'description' => 'Opera Browser.',
                'is_system' => true,
            ],
            [
                'name' => 'Samsung Internet',
                'slug' => 'samsung_internet',
                'icon_name' => 'language',
                'color_hex' => '#4F46E5',
                'description' => 'Samsung Internet Browser.',
                'is_system' => true,
            ],
            [
                'name' => 'Unknown',
                'slug' => 'unknown',
                'icon_name' => 'help_outline',
                'color_hex' => '#64748B',
                'description' => 'Navegador não identificado.',
                'is_system' => true,
            ],
        ];

        foreach ($items as $item) {
            Browser::query()->updateOrCreate(
                ['slug' => $item['slug']],
                $item
            );
        }
    }
}
