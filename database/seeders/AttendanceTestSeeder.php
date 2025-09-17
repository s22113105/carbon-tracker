<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Trip;
use App\Models\User;
use App\Models\CarbonEmission;
use App\Services\TransportAnalysisService;
use Carbon\Carbon;

class AttendanceTestSeeder extends Seeder
{
    public function run()
    {
        $user = User::where('email', 'user@example.com')->first();
        $analysisService = new TransportAnalysisService();
        
        if ($user) {
            // 清除舊資料
            Trip::where('user_id', $user->id)->delete();
            CarbonEmission::where('user_id', $user->id)->delete();
            
            // 建立過去一週的打卡記錄
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);
                
                if ($date->isWeekend()) {
                    continue;
                }
                
                // 上班行程 - 模擬不同交通工具
                $scenarios = [
                    ['distance' => 2.5, 'duration' => 8, 'transport' => 'walking'],  // 步行
                    ['distance' => 8.0, 'duration' => 25, 'transport' => 'bus'],     // 公車
                    ['distance' => 12.0, 'duration' => 24, 'transport' => 'mrt'],    // 捷運
                    ['distance' => 15.0, 'duration' => 35, 'transport' => 'car'],    // 汽車
                    ['distance' => 10.0, 'duration' => 20, 'transport' => 'motorcycle'], // 機車
                ];
                
                $scenario = $scenarios[$i % count($scenarios)];
                
                // 上班行程
                $toWorkTrip = Trip::create([
                    'user_id' => $user->id,
                    'start_time' => $date->copy()->setTime(8, rand(30, 59)),
                    'end_time' => $date->copy()->setTime(8, rand(30, 59))->addMinutes($scenario['duration']),
                    'start_latitude' => 25.0330,
                    'start_longitude' => 121.5654,
                    'end_latitude' => 25.0478,
                    'end_longitude' => 121.5318,
                    'distance' => $scenario['distance'],
                    'transport_mode' => $scenario['transport'],
                    'trip_type' => 'to_work',
                ]);
                
                // 分析並建立碳排放記錄
                $analysis = $analysisService->analyzeTransport($scenario['distance'], $scenario['duration']);
                
                CarbonEmission::create([
                    'user_id' => $user->id,
                    'trip_id' => $toWorkTrip->id,
                    'emission_date' => $date,
                    'transport_mode' => $analysis['transport_mode'],
                    'distance' => $scenario['distance'],
                    'co2_emission' => $analysis['co2_emission'],
                ]);
                
                // 下班行程（相同邏輯）
                $fromWorkTrip = Trip::create([
                    'user_id' => $user->id,
                    'start_time' => $date->copy()->setTime(17, rand(30, 59)),
                    'end_time' => $date->copy()->setTime(17, rand(30, 59))->addMinutes($scenario['duration']),
                    'start_latitude' => 25.0478,
                    'start_longitude' => 121.5318,
                    'end_latitude' => 25.0330,
                    'end_longitude' => 121.5654,
                    'distance' => $scenario['distance'],
                    'transport_mode' => $scenario['transport'],
                    'trip_type' => 'from_work',
                ]);
                
                CarbonEmission::create([
                    'user_id' => $user->id,
                    'trip_id' => $fromWorkTrip->id,
                    'emission_date' => $date,
                    'transport_mode' => $analysis['transport_mode'],
                    'distance' => $scenario['distance'],
                    'co2_emission' => $analysis['co2_emission'],
                ]);
            }
        }
    }
}