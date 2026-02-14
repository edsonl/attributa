<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TimezonesSeeder extends Seeder
{
    public function run(): void
    {
        $timezones = require database_path('seeders/data/timezones.php');

        foreach ($timezones as $timezone) {
            DB::table('timezones')->updateOrInsert(
                ['identifier' => $timezone['identifier']],
                array_merge($timezone, [
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
