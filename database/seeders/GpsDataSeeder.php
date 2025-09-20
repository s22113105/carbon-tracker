<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GpsData;
use App\Models\User;
use Carbon\Carbon;

class GpsDataSeeder extends Seeder
{
    /**
     * 為使用者產生測試用的GPS資料
     */
    public function run(): void
    {
        // 獲取第一個使用者，如果沒有就創建一個
        $user = User::first();
        if (!$user) {
            $user = User::factory()->create([
                'name' => '測試使用者',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
            ]);
        }

        // 產生最近7天的GPS資料
        for ($day = 6; $day >= 0; $day--) {
            $date = Carbon::now()->subDays($day);
            $this->createDayGpsData($user->id, $date);
        }
    }

    /**
     * 為特定日期創建GPS資料
     */
    private function createDayGpsData($userId, $date)
    {
        // 假設的起始位置（高雄市政府附近）
        $homeLatitude = 22.6203348;
        $homeLongitude = 120.3120375;
        
        // 假設的工作位置
        $workLatitude = 22.6273685;
        $workLongitude = 120.3014479;

        // 早上通勤 (08:00-09:00) - 從家到公司
        $this->createCommute($userId, $date, '08:00:00', '09:00:00', 
                           $homeLatitude, $homeLongitude, 
                           $workLatitude, $workLongitude, 'morning');

        // 中午外出 (12:00-13:00) - 短途移動
        $this->createLunchTrip($userId, $date, '12:00:00', '13:00:00', 
                              $workLatitude, $workLongitude);

        // 晚上通勤 (18:00-19:00) - 從公司回家
        $this->createCommute($userId, $date, '18:00:00', '19:00:00', 
                           $workLatitude, $workLongitude, 
                           $homeLatitude, $homeLongitude, 'evening');

        // 週末活動
        if ($date->isWeekend()) {
            $this->createWeekendActivity($userId, $date);
        }
    }

    /**
     * 創建通勤資料
     */
    private function createCommute($userId, $date, $startTime, $endTime, $startLat, $startLng, $endLat, $endLng, $period)
    {
        $start = Carbon::parse($date->format('Y-m-d') . ' ' . $startTime);
        $end = Carbon::parse($date->format('Y-m-d') . ' ' . $endTime);
        
        // 決定交通工具類型（隨機選擇）
        $transportTypes = [
            'walking' => ['speed_range' => [3, 6], 'points' => 20],
            'bicycle' => ['speed_range' => [12, 20], 'points' => 15],
            'motorcycle' => ['speed_range' => [25, 45], 'points' => 10],
            'car' => ['speed_range' => [20, 60], 'points' => 12],
            'bus' => ['speed_range' => [15, 40], 'points' => 18], // 停停走走
        ];
        
        $transportType = array_rand($transportTypes);
        $config = $transportTypes[$transportType];
        
        $totalMinutes = $start->diffInMinutes($end);
        $pointCount = $config['points'];
        $intervalMinutes = $totalMinutes / $pointCount;
        
        for ($i = 0; $i <= $pointCount; $i++) {
            $timestamp = $start->copy()->addMinutes($intervalMinutes * $i);
            
            // 線性插值計算位置
            $ratio = $pointCount > 0 ? $i / $pointCount : 0;
            $latitude = $startLat + ($endLat - $startLat) * $ratio;
            $longitude = $startLng + ($endLng - $startLng) * $ratio;
            
            // 加入一些隨機偏移模擬實際移動
            $latitude += (rand(-100, 100) / 1000000);
            $longitude += (rand(-100, 100) / 1000000);
            
            // 計算速度
            $speed = $this->calculateSpeed($transportType, $config['speed_range'], $i, $pointCount);
            
            GpsData::create([
                'user_id' => $userId,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'speed' => $speed,
                'altitude' => rand(5, 50),
                'accuracy' => rand(3, 8),
                'timestamp' => $timestamp,
                'date' => $timestamp->format('Y-m-d'),
                'time' => $timestamp->format('H:i:s'),
                'device_id' => 'ESP32_TEST_001',
            ]);
        }
    }

    /**
     * 創建午餐時間的短途移動
     */
    private function createLunchTrip($userId, $date, $startTime, $endTime, $centerLat, $centerLng)
    {
        $start = Carbon::parse($date->format('Y-m-d') . ' ' . $startTime);
        $end = Carbon::parse($date->format('Y-m-d') . ' ' . $endTime);
        
        // 步行到附近餐廳
        $pointCount = 8;
        $totalMinutes = $start->diffInMinutes($end);
        $intervalMinutes = $totalMinutes / $pointCount;
        
        for ($i = 0; $i <= $pointCount; $i++) {
            $timestamp = $start->copy()->addMinutes($intervalMinutes * $i);
            
            // 在中心點附近隨機移動
            $radius = 0.003; // 約300公尺範圍
            $angle = rand(0, 360) * pi() / 180;
            $distance = rand(0, 100) / 100 * $radius;
            
            $latitude = $centerLat + $distance * cos($angle);
            $longitude = $centerLng + $distance * sin($angle);
            
            $speed = rand(2, 6); // 步行速度
            
            GpsData::create([
                'user_id' => $userId,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'speed' => $speed,
                'altitude' => rand(5, 50),
                'accuracy' => rand(3, 8),
                'timestamp' => $timestamp,
                'date' => $timestamp->format('Y-m-d'),
                'time' => $timestamp->format('H:i:s'),
                'device_id' => 'ESP32_TEST_001',
            ]);
        }
    }

    /**
     * 創建週末活動資料
     */
    private function createWeekendActivity($userId, $date)
    {
        // 週末外出活動 (14:00-17:00)
        $start = Carbon::parse($date->format('Y-m-d') . ' 14:00:00');
        $end = Carbon::parse($date->format('Y-m-d') . ' 17:00:00');
        
        // 起始位置（家）
        $homeLat = 22.6203348;
        $homeLng = 120.3120375;
        
        // 目的地（隨機選擇：購物中心、公園等）
        $destinations = [
            ['lat' => 22.6138, 'lng' => 120.3015, 'name' => '漢神百貨'],
            ['lat' => 22.6219, 'lng' => 120.2960, 'name' => '愛河'],
            ['lat' => 22.6156, 'lng' => 120.3133, 'name' => '夢時代'],
        ];
        
        $destination = $destinations[array_rand($destinations)];
        
        // 使用不同交通工具（週末較可能開車或騎車）
        $transportTypes = ['car', 'motorcycle', 'bicycle'];
        $transportType = $transportTypes[array_rand($transportTypes)];
        
        $this->createCommute($userId, $date, '14:00:00', '15:30:00', 
                           $homeLat, $homeLng, 
                           $destination['lat'], $destination['lng'], 'weekend_out');
        
        // 回程
        $this->createCommute($userId, $date, '16:30:00', '17:00:00', 
                           $destination['lat'], $destination['lng'],
                           $homeLat, $homeLng, 'weekend_back');
    }

    /**
     * 根據交通工具類型計算速度
     */
    private function calculateSpeed($transportType, $speedRange, $currentPoint, $totalPoints)
    {
        $minSpeed = $speedRange[0];
        $maxSpeed = $speedRange[1];
        
        // 基本速度
        $baseSpeed = rand($minSpeed, $maxSpeed);
        
        // 根據交通工具調整速度模式
        switch ($transportType) {
            case 'walking':
                // 步行速度相對穩定
                return $baseSpeed + rand(-1, 1);
                
            case 'bicycle':
                // 腳踏車速度有小幅波動
                return $baseSpeed + rand(-3, 3);
                
            case 'motorcycle':
                // 機車在市區會有較大速度變化
                if (rand(1, 10) <= 3) { // 30% 機率停紅燈
                    return rand(0, 5);
                }
                return $baseSpeed + rand(-10, 15);
                
            case 'car':
                // 汽車在交通中速度變化大
                if (rand(1, 10) <= 4) { // 40% 機率塞車或停車
                    return rand(0, 10);
                }
                return $baseSpeed + rand(-15, 20);
                
            case 'bus':
                // 公車停停走走
                if (rand(1, 10) <= 5) { // 50% 機率停站
                    return rand(0, 5);
                }
                return $baseSpeed + rand(-8, 12);
                
            default:
                return $baseSpeed;
        }
    }
}