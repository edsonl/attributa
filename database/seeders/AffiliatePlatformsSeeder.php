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
            'dr_cash'        => 'Dr Cash',
            'clickbank'      => 'ClickBank',
            'maxweb'         => 'MaxWeb',
            'everad'         => 'Everad',
            'adcombo'        => 'AdCombo',
            'cpagetti'       => 'CPAGetti',
            'terra_leads'    => 'Terra Leads',
            'leadbit'        => 'Leadbit',
            'crakrevenue'    => 'CrakRevenue',
            'zeydoo'         => 'Zeydoo',
        ];

        foreach ($platforms as $name) {
            DB::table('affiliate_platforms')->updateOrInsert(
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
