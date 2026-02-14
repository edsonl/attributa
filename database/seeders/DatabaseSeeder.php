<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            IpCategoriesSeeder::class,
            AffiliatePlatformsSeeder::class,
            ChannelsSeeder::class,
            CountriesSeeder::class,
            TimezonesSeeder::class,
            DevUserSeeder::class,
            TestCampaignSeeder::class,
            TestPageviewsSeeder::class,
            TestAdsConversionsSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call([
                TestCampaignSeeder::class,
                TestPageviewsSeeder::class,
                TestAdsConversionsSeeder::class,
            ]);
        }
    }
}
