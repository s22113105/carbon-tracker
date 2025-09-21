<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Trip;
use App\Models\User;
use App\Models\CarbonEmissionAnalysis;
use Carbon\Carbon;

class AttendanceTestSeeder extends Seeder
{
    public function run()
    {
        $user = User::where('email', 'user@example.com')->first();
        
        if ($user) {
            // 清除舊資料
            Trip::where('user_id', $user->id)->delete();
            CarbonEmissionAnalysis::where('user_id', $user->id)->delete();
            
            // 建立過去一週的打卡記錄
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);
                
                if ($date->isWeekend()) {
                    continue;
                }
                
                // 上班行程 - 模擬不同交通工具
                $scenarios = [
                    ['distance' => 2.5, 'duration' => 480, 'transport' => 'walking'],     // 步行 8分鐘
                    ['distance' => 8.0, 'duration' => 1500, 'transport' => 'bus'],       // 公車 25分鐘
                    ['distance' => 12.0, 'duration' => 1440, 'transport' => 'mixed'],    // 捷運 24分鐘 (改為mixed)
                    ['distance' => 15.0, 'duration' => 2100, 'transport' => 'car'],      // 汽車 35分鐘
                    ['distance' => 10.0, 'duration' => 1200, 'transport' => 'motorcycle'], // 機車 20分鐘
                ];
                
                $scenario = $scenarios[$i % count($scenarios)];
                
                // 計算平均速度
                $averageSpeed = round(($scenario['distance'] / ($scenario['duration'] / 3600)), 2);
                
                // 計算碳排放量 (假資料)
                $carbonEmission = $this->calculateCarbonEmission($scenario['transport'], $scenario['distance'] * 2); // 上下班來回
                
                // 上班行程
                $toWorkTrip = Trip::create([
                    'user_id' => $user->id,
                    'start_time' => $date->copy()->setTime(8, rand(30, 59)),
                    'end_time' => $date->copy()->setTime(8, rand(30, 59))->addSeconds($scenario['duration']),
                    'start_latitude' => 25.0330,
                    'start_longitude' => 121.5654,
                    'end_latitude' => 25.0478,
                    'end_longitude' => 121.5318,
                    'distance' => $scenario['distance'],
                    'transport_mode' => $scenario['transport'],
                    'trip_type' => 'to_work',
                ]);
                
                // 下班行程
                $fromWorkTrip = Trip::create([
                    'user_id' => $user->id,
                    'start_time' => $date->copy()->setTime(17, rand(30, 59)),
                    'end_time' => $date->copy()->setTime(17, rand(30, 59))->addSeconds($scenario['duration']),
                    'start_latitude' => 25.0478,
                    'start_longitude' => 121.5318,
                    'end_latitude' => 25.0330,
                    'end_longitude' => 121.5654,
                    'distance' => $scenario['distance'],
                    'transport_mode' => $scenario['transport'],
                    'trip_type' => 'from_work',
                ]);
                
                // 建立當日碳排放分析記錄 (每日一筆，包含上下班總計)
                CarbonEmissionAnalysis::create([
                    'user_id' => $user->id,
                    'analysis_date' => $date,
                    'total_distance' => $scenario['distance'] * 2, // 來回距離
                    'total_duration' => $scenario['duration'] * 2,  // 來回時間
                    'transport_mode' => $scenario['transport'],
                    'carbon_emission' => $carbonEmission,
                    'route_details' => json_encode([
                        'start_address' => '台北市信義區信義路五段7號',
                        'end_address' => '台北市中正區重慶南路一段122號',
                        'route_type' => 'optimal'
                    ]),
                    'ai_analysis' => json_encode([
                        'efficiency_score' => rand(70, 95),
                        'environmental_rating' => $this->getEnvironmentalRating($scenario['transport']),
                        'peak_hour_factor' => rand(80, 120) / 100
                    ]),
                    'suggestions' => $this->getSuggestions($scenario['transport']),
                    'average_speed' => $averageSpeed,
                ]);
            }
            
            $this->command->info('成功建立測試資料！');
        }
    }
    
    /**
     * 計算碳排放量 (簡單假資料)
     */
    private function calculateCarbonEmission($transport, $totalDistance)
    {
        $factors = [
            'walking' => 0,
            'bicycle' => 0,
            'motorcycle' => 0.084,
            'car' => 0.192,
            'bus' => 0.089,
            'mixed' => 0.120,
        ];
        
        return round($totalDistance * ($factors[$transport] ?? 0.150), 3);
    }
    
    /**
     * 環境評級
     */
    private function getEnvironmentalRating($transport)
    {
        $ratings = [
            'walking' => 'A+',
            'bicycle' => 'A+',
            'bus' => 'B+',
            'mixed' => 'B',
            'motorcycle' => 'C',
            'car' => 'D',
        ];
        
        return $ratings[$transport] ?? 'C';
    }
    
    /**
     * 簡單建議
     */
    private function getSuggestions($transport)
    {
        $suggestions = [
            'walking' => '步行是最環保的選擇，請繼續保持！',
            'bicycle' => '騎自行車零碳排放且有益健康，很棒的選擇！',
            'bus' => '使用大眾運輸是不錯的選擇，考慮搭配步行更環保。',
            'mixed' => '混合運輸不錯，可嘗試增加步行或自行車比例。',
            'motorcycle' => '建議短距離改用自行車，長距離考慮大眾運輸。',
            'car' => '建議改用大眾運輸、共乘或自行車來減少碳排放。',
        ];
        
        return $suggestions[$transport] ?? '建議選擇更環保的交通方式。';
    }
}