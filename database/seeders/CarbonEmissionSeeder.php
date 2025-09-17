<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CarbonEmission;
use App\Models\User;

class CarbonEmissionSeeder extends Seeder
{
    public function run()
    {
        // 先建立一個測試用的一般使用者
        $user = User::create([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'role' => 'user',
        ]);

        // 為這個使用者建立測試資料
        CarbonEmission::create([
            'user_id' => $user->id,
            'emission_date' => today(),
            'transport_mode' => 'car',
            'distance' => 10.5,
            'co2_emission' => 2.1,
        ]);

        CarbonEmission::create([
            'user_id' => $user->id,
            'emission_date' => today()->subDays(2),
            'transport_mode' => 'bus',
            'distance' => 8.0,
            'co2_emission' => 0.8,
        ]);

        CarbonEmission::create([
            'user_id' => $user->id,
            'emission_date' => today()->subWeek(),
            'transport_mode' => 'mrt',
            'distance' => 15.0,
            'co2_emission' => 1.2,
        ]);
    }
}