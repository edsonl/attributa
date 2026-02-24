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
                    'sub1' => 'sub1',
                    'sub2' => 'sub2',
                    'sub3' => 'sub3',
                    'sub4' => 'sub4',
                    'sub5' => 'sub5',
                ],
                'lead_param_mapping' => [
                    'payout_amount' => 'payment',
                    'currency_code' => 'currency',
                    'lead_status' => 'status',
                    'platform_lead_id' => 'uuid',
                    'occurred_at' => 'date',
                    'offer_id' => 'offer',
                ],
                'lead_status_mapping' => [
                    'approved' => 'approved',
                    'rejected' => 'rejected',
                    'trash' => 'trash',
                    'pending' => 'processing',
                    'hold' => 'processing',
                    'cancelled' => 'cancelled',
                    'canceled' => 'cancelled',
                    'refunded' => 'refunded',
                    'refund' => 'refunded',
                    'chargeback' => 'chargeback',
                    'charge_back' => 'chargeback',
                    'charge-back' => 'chargeback',
                ],
                'postback_additional_params' => [
                    'uuid',
                    'date',
                    'status',
                    'ip',
                    'payment',
                    'offer',
                    'stream',
                    'currency',
                ],
            ],
            'clickbank' => ['name' => 'ClickBank', 'tracking_param_mapping' => [], 'lead_param_mapping' => [], 'lead_status_mapping' => [], 'postback_additional_params' => []],
            'maxweb' => ['name' => 'MaxWeb', 'tracking_param_mapping' => [], 'lead_param_mapping' => [], 'lead_status_mapping' => [], 'postback_additional_params' => []],
            'everad' => ['name' => 'Everad', 'tracking_param_mapping' => [], 'lead_param_mapping' => [], 'lead_status_mapping' => [], 'postback_additional_params' => []],
            'adcombo' => ['name' => 'AdCombo', 'tracking_param_mapping' => [], 'lead_param_mapping' => [], 'lead_status_mapping' => [], 'postback_additional_params' => []],
            'cpagetti' => ['name' => 'CPAGetti', 'tracking_param_mapping' => [], 'lead_param_mapping' => [], 'lead_status_mapping' => [], 'postback_additional_params' => []],
            'terra_leads' => ['name' => 'Terra Leads', 'tracking_param_mapping' => [], 'lead_param_mapping' => [], 'lead_status_mapping' => [], 'postback_additional_params' => []],
            'leadbit' => ['name' => 'Leadbit', 'tracking_param_mapping' => [], 'lead_param_mapping' => [], 'lead_status_mapping' => [], 'postback_additional_params' => []],
            'crakrevenue' => ['name' => 'CrakRevenue', 'tracking_param_mapping' => [], 'lead_param_mapping' => [], 'lead_status_mapping' => [], 'postback_additional_params' => []],
            'zeydoo' => ['name' => 'Zeydoo', 'tracking_param_mapping' => [], 'lead_param_mapping' => [], 'lead_status_mapping' => [], 'postback_additional_params' => []],
        ];

        foreach ($platforms as $slug => $platform) {
            DB::table('affiliate_platforms')->updateOrInsert(
                ['slug' => Str::slug($slug, '_')],
                [
                    'name' => $platform['name'],
                    'active' => true,
                    'integration_type' => 'postback_get',
                    'tracking_param_mapping' => json_encode($platform['tracking_param_mapping']),
                    'lead_param_mapping' => json_encode($platform['lead_param_mapping'] ?? []),
                    'lead_status_mapping' => json_encode($platform['lead_status_mapping'] ?? []),
                    'postback_additional_params' => json_encode($platform['postback_additional_params']),
                    'created_at'=> now(),
                    'updated_at'=> now(),
                ]
            );
        }
    }
}
