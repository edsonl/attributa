<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IpCategory;

class IpCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [

            [
                'name'        => 'Real',
                'slug'        => 'real',
                'color_hex'   => '#16A34A',
                'description' => 'Tráfego legítimo de usuário real.',
                'is_system'   => true,
            ],

            [
                'name'        => 'Googlebot',
                'slug'        => 'googlebot',
                'color_hex'   => '#0EA5E9',
                'description' => 'Crawler oficial do Google validado por DNS reverso.',
                'is_system'   => true,
            ],

            [
                'name'        => 'Bot',
                'slug'        => 'bot',
                'color_hex'   => '#DC2626',
                'description' => 'Bot ou crawler automatizado não identificado como Googlebot.',
                'is_system'   => true,
            ],

            [
                'name'        => 'VPN',
                'slug'        => 'vpn',
                'color_hex'   => '#F59E0B',
                'description' => 'IP identificado como VPN.',
                'is_system'   => true,
            ],

            [
                'name'        => 'Proxy',
                'slug'        => 'proxy',
                'color_hex'   => '#F97316',
                'description' => 'IP identificado como proxy.',
                'is_system'   => true,
            ],

            [
                'name'        => 'Tor',
                'slug'        => 'tor',
                'color_hex'   => '#9333EA',
                'description' => 'IP identificado como nó da rede Tor.',
                'is_system'   => true,
            ],

            [
                'name'        => 'Datacenter',
                'slug'        => 'datacenter',
                'color_hex'   => '#7C3AED',
                'description' => 'IP pertencente a datacenter ou servidor.',
                'is_system'   => true,
            ],

            [
                'name'        => 'Proprietário',
                'slug'        => 'owner',
                'color_hex'   => '#2563EB',
                'description' => 'IP do usuário do sistema (modo teste).',
                'is_system'   => true,
            ],

            [
                'name'        => 'Unknown',
                'slug'        => 'unknown',
                'color_hex'   => '#6B7280',
                'description' => 'IP analisado mas não foi possível determinar categoria.',
                'is_system'   => true,
            ],
        ];

        foreach ($categories as $category) {
            IpCategory::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
