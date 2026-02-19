<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AffiliatePlatformsSeeder extends Seeder
{
    public function run(): void
    {


        $platforms = [
            'dr_cash' => [
                'name' => 'Dr Cash',
                'tracking_param_mapping' => [
                    'sub1' => 'subid1',
                    'sub2' => 'subid2',
                    'sub3' => 'subid3',
                    'sub4' => 'subid4',
                    'sub5' => 'subid5',
                ],
                'conversion_param_mapping' => [
                    'conversion_value' => 'amount',
                    'currency_code' => 'currency',
                ],
                'postback_additional_params' => [
                    'uid',
                    'date',
                    'status',
                    'ip',
                    'payment',
                    'offer',
                    'stream',
                    'currency',
                ],
            ],
            'clickbank' => ['name' => 'ClickBank', 'tracking_param_mapping' => [], 'conversion_param_mapping' => [], 'postback_additional_params' => []],
            'maxweb' => ['name' => 'MaxWeb', 'tracking_param_mapping' => [], 'conversion_param_mapping' => [], 'postback_additional_params' => []],
            'everad' => ['name' => 'Everad', 'tracking_param_mapping' => [], 'conversion_param_mapping' => [], 'postback_additional_params' => []],
            'adcombo' => ['name' => 'AdCombo', 'tracking_param_mapping' => [], 'conversion_param_mapping' => [], 'postback_additional_params' => []],
            'cpagetti' => ['name' => 'CPAGetti', 'tracking_param_mapping' => [], 'conversion_param_mapping' => [], 'postback_additional_params' => []],
            'terra_leads' => ['name' => 'Terra Leads', 'tracking_param_mapping' => [], 'conversion_param_mapping' => [], 'postback_additional_params' => []],
            'leadbit' => ['name' => 'Leadbit', 'tracking_param_mapping' => [], 'conversion_param_mapping' => [], 'postback_additional_params' => []],
            'crakrevenue' => ['name' => 'CrakRevenue', 'tracking_param_mapping' => [], 'conversion_param_mapping' => [], 'postback_additional_params' => []],
            'zeydoo' => ['name' => 'Zeydoo', 'tracking_param_mapping' => [], 'conversion_param_mapping' => [], 'postback_additional_params' => []],
        ];

        foreach ($platforms as $slug => $platform) {
            DB::table('affiliate_platforms')->updateOrInsert(
                ['slug' => Str::slug($slug, '_')],
                [
                    'name' => $platform['name'],
                    'active' => true,
                    'integration_type' => 'postback_get',
                    'tracking_param_mapping' => json_encode($platform['tracking_param_mapping']),
                    'conversion_param_mapping' => json_encode($platform['conversion_param_mapping'] ?? []),
                    'postback_additional_params' => json_encode($platform['postback_additional_params']),
                    'created_at'=> now(),
                    'updated_at'=> now(),
                ]
            );
        }
    }
}
