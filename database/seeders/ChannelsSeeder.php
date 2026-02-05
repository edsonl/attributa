<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChannelsSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            'Google Ads',
            'Meta Ads',
            'TikTok Ads',
            'WhatsApp',
            'Landing Page',
            'Afiliados',
            'Orgânico / SEO',
            'E-mail Marketing',
        ];

        foreach ($channels as $name) {
            DB::table('channels')->updateOrInsert(
                ['slug' => Str::slug($name, '_')],
                [
                    'name'       => $name,
                    'active'     => true,
                    'created_at'=> now(),
                    'updated_at'=> now(),
                ]
            );
        }
    }
}
