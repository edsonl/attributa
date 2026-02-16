<?php

namespace Database\Seeders;

use App\Models\DeviceCategory;
use Illuminate\Database\Seeder;

class DeviceCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'name' => 'Desktop',
                'slug' => 'desktop',
                'icon_name' => 'desktop_windows',
                'color_hex' => '#2563EB',
                'description' => 'Computador desktop ou notebook.',
                'is_system' => true,
            ],
            [
                'name' => 'Mobile',
                'slug' => 'mobile',
                'icon_name' => 'smartphone',
                'color_hex' => '#16A34A',
                'description' => 'Smartphone.',
                'is_system' => true,
            ],
            [
                'name' => 'Tablet',
                'slug' => 'tablet',
                'icon_name' => 'tablet_mac',
                'color_hex' => '#0EA5E9',
                'description' => 'Tablet.',
                'is_system' => true,
            ],
            [
                'name' => 'Smart TV',
                'slug' => 'smart_tv',
                'icon_name' => 'tv',
                'color_hex' => '#8B5CF6',
                'description' => 'Smart TV ou TV browser.',
                'is_system' => true,
            ],
            [
                'name' => 'Bot',
                'slug' => 'bot',
                'icon_name' => 'smart_toy',
                'color_hex' => '#DC2626',
                'description' => 'Crawler ou bot automatizado.',
                'is_system' => true,
            ],
            [
                'name' => 'Desconhecido',
                'slug' => 'unknown',
                'icon_name' => 'devices_other',
                'color_hex' => '#64748B',
                'description' => 'Tipo de dispositivo não identificado.',
                'is_system' => true,
            ],
        ];

        foreach ($items as $item) {
            DeviceCategory::query()->updateOrCreate(
                ['slug' => $item['slug']],
                $item
            );
        }
    }
}
