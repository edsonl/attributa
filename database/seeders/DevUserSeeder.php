<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DevUserSeeder extends Seeder
{
    public function run(): void
    {
        
        if (! app()->environment('local')) {
            return;
        }

        User::updateOrCreate(
            [
                'email' => 'edson@master.dev.br',
            ],
            [
                'name'     => 'Edson (Dev)',
                'password' => Hash::make('teste123'),
                'email_verified_at' => now(),
            ]
        );
    }
}
