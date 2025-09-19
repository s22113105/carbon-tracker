<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GpsData;
use Carbon\Carbon;

class GpsDataSeeder extends Seeder
{
    public function run()
    {
        // 生成最近15天的GPS資料
        for ($i = 14; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            
            // 每天生成20-50筆資料
            $recordsCount = rand(20, 50);
            
            for ($j = 0; $j < $recordsCount; $j++) {
                $time = $date->copy()->addMinutes(rand(0, 1439)); // 隨機時間
                
                GpsData::create([
                    'user_id' => 1,
                    'latitude' => $this->faker->latitude(22.5, 22.8),
                    'longitude' => $this->faker->longitude(120.2, 120.4),
                    'speed' => $this->generateRealisticSpeed(),
                    'altitude' => rand(0, 100),
                    'accuracy' => rand(1, 10),
                    'date' => $time->format('Y-m-d'),
                    'time' => $time->format('H:i:s'),
                    'created_at' => $time,
                    'updated_at' => $time,
                ]);
            }
        }
    }
    
    private function generateRealisticSpeed()
    {
        $speedTypes = [
            'walking' => [0, 8],
            'bicycle' => [8, 25],
            'motorcycle' => [25, 60],
            'car' => [30, 120],
            'bus' => [20, 80]
        ];
        
        $type = array_rand($speedTypes);
        [$min, $max] = $speedTypes[$type];
        
        return rand($min * 100, $max * 100) / 100;
    }
}