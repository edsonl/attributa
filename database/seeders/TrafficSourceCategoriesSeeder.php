<?php

namespace Database\Seeders;

use App\Models\TrafficSourceCategory;
use Illuminate\Database\Seeder;

class TrafficSourceCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'name' => 'Pago',
                'slug' => 'paid',
                'icon_name' => 'payments',
                'color_hex' => '#2563EB',
                'description' => 'Tráfego com forte sinal de mídia paga.',
                'is_system' => true,
            ],
            [
                'name' => 'Orgânico',
                'slug' => 'organic',
                'icon_name' => 'search',
                'color_hex' => '#16A34A',
                'description' => 'Tráfego orgânico de buscadores.',
                'is_system' => true,
            ],
            [
                'name' => 'Social',
                'slug' => 'social',
                'icon_name' => 'groups',
                'color_hex' => '#8B5CF6',
                'description' => 'Tráfego vindo de redes sociais.',
                'is_system' => true,
            ],
            [
                'name' => 'Referência',
                'slug' => 'referral',
                'icon_name' => 'link',
                'color_hex' => '#F59E0B',
                'description' => 'Tráfego vindo de outro site (não buscador/social).',
                'is_system' => true,
            ],
            [
                'name' => 'Direto',
                'slug' => 'direct',
                'icon_name' => 'north_east',
                'color_hex' => '#0EA5E9',
                'description' => 'Tráfego sem referenciador e sem parâmetros de aquisição.',
                'is_system' => true,
            ],
            [
                'name' => 'Desconhecido',
                'slug' => 'unknown',
                'icon_name' => 'help_outline',
                'color_hex' => '#64748B',
                'description' => 'Não foi possível determinar a origem do tráfego.',
                'is_system' => true,
            ],
        ];

        foreach ($items as $item) {
            TrafficSourceCategory::query()->updateOrCreate(
                ['slug' => $item['slug']],
                $item
            );
        }
    }
}
