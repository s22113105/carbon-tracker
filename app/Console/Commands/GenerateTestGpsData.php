<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GpsRecord;
use App\Models\User;
use Carbon\Carbon;

class GenerateTestGpsData extends Command
{
    protected $signature = 'gps:generate-test-data 
                            {--user= : 用戶ID，不指定則使用第一個用戶}
                            {--date= : 日期 (Y-m-d)，不指定則使用今天}
                            {--type=commute : 路線類型 (commute|shopping|random)}';

    protected $description = '生成GPS測試資料用於開發測試';

    public function handle()
    {
        $userId = $this->option('user') ?: 2;
        $date = $this->option('date') ?: Carbon::today()->format('Y-m-d');
        $type = $this->option('type') ?: 'commute';

        $this->info("生成GPS測試資料...");
        $this->info("用戶ID: {$userId}");
        $this->info("日期: {$date}");
        $this->info("類型: {$type}");

        // 清除現有資料
        $deletedCount = GpsRecord::where('user_id', $userId)
            ->whereDate('recorded_at', $date)
            ->delete();
            
        if ($deletedCount > 0) {
            $this->warn("已刪除 {$deletedCount} 筆現有GPS資料");
        }

        $gpsData = [];
        
        switch ($type) {
            case 'commute':
                $gpsData = $this->generateCommuteRoute($userId, $date);
                break;
            case 'shopping':
                $gpsData = $this->generateShoppingRoute($userId, $date);
                break;
            case 'random':
                $gpsData = $this->generateRandomRoute($userId, $date);
                break;
        }

        // 批量插入
        GpsRecord::insert($gpsData);
        
        $this->info("成功生成 " . count($gpsData) . " 筆GPS測試資料");
        $this->info("請到地圖頁面點擊「重新分析行程」來生成行程記錄");
        
        return 0;
    }

    private function generateCommuteRoute($userId, $date)
    {
        // 高雄通勤路線：從住家到樹德科技大學
        $homeLocation = ['lat' => 22.7500, 'lng' => 120.3600]; // 模擬住家
        $schoolLocation = ['lat' => 22.7632, 'lng' => 120.3757]; // 樹德科技大學
        
        $gpsData = [];
        
        // 早上通勤 (08:00-08:30)
        $morningRoute = $this->generateRoute(
            $homeLocation, 
            $schoolLocation, 
            Carbon::parse($date . ' 08:00:00'),
            30 // 30分鐘
        );
        $gpsData = array_merge($gpsData, $morningRoute);
        
        // 午餐外出 (12:00-13:00)
        $restaurantLocation = ['lat' => 22.7650, 'lng' => 120.3780];
        $lunchRoute1 = $this->generateRoute(
            $schoolLocation,
            $restaurantLocation,
            Carbon::parse($date . ' 12:00:00'),
            10
        );
        $lunchRoute2 = $this->generateRoute(
            $restaurantLocation,
            $schoolLocation,
            Carbon::parse($date . ' 12:50:00'),
            10
        );
        $gpsData = array_merge($gpsData, $lunchRoute1, $lunchRoute2);
        
        // 下班回家 (17:30-18:00)
        $eveningRoute = $this->generateRoute(
            $schoolLocation,
            $homeLocation,
            Carbon::parse($date . ' 17:30:00'),
            30
        );
        $gpsData = array_merge($gpsData, $eveningRoute);
        
        return $this->formatGpsData($gpsData, $userId);
    }

    private function generateShoppingRoute($userId, $date)
    {
        // 購物路線
        $homeLocation = ['lat' => 22.7500, 'lng' => 120.3600];
        $mallLocation = ['lat' => 22.7700, 'lng' => 120.3500]; // 購物中心
        
        $gpsData = [];
        
        // 去購物中心 (14:00-14:20)
        $route1 = $this->generateRoute(
            $homeLocation,
            $mallLocation,
            Carbon::parse($date . ' 14:00:00'),
            20
        );
        
        // 回家 (16:30-16:50)
        $route2 = $this->generateRoute(
            $mallLocation,
            $homeLocation,
            Carbon::parse($date . ' 16:30:00'),
            20
        );
        
        $gpsData = array_merge($gpsData, $route1, $route2);
        
        return $this->formatGpsData($gpsData, $userId);
    }

    private function generateRandomRoute($userId, $date)
    {
        // 隨機路線
        $startLocation = ['lat' => 22.7500, 'lng' => 120.3600];
        $gpsData = [];
        
        $currentTime = Carbon::parse($date . ' 10:00:00');
        $currentLocation = $startLocation;
        
        // 生成5個隨機移動
        for ($i = 0; $i < 5; $i++) {
            $targetLocation = [
                'lat' => $currentLocation['lat'] + (rand(-100, 100) / 10000),
                'lng' => $currentLocation['lng'] + (rand(-100, 100) / 10000)
            ];
            
            $route = $this->generateRoute(
                $currentLocation,
                $targetLocation,
                $currentTime,
                rand(15, 45) // 15-45分鐘
            );
            
            $gpsData = array_merge($gpsData, $route);
            
            $currentLocation = $targetLocation;
            $currentTime = $currentTime->addMinutes(rand(60, 120)); // 間隔1-2小時
        }
        
        return $this->formatGpsData($gpsData, $userId);
    }

    private function generateRoute($startLocation, $endLocation, $startTime, $durationMinutes)
    {
        $route = [];
        $totalPoints = max(10, $durationMinutes); // 至少10個點
        
        for ($i = 0; $i <= $totalPoints; $i++) {
            $progress = $i / $totalPoints;
            
            // 線性插值計算位置
            $lat = $startLocation['lat'] + ($endLocation['lat'] - $startLocation['lat']) * $progress;
            $lng = $startLocation['lng'] + ($endLocation['lng'] - $startLocation['lng']) * $progress;
            
            // 添加隨機誤差模擬GPS精度
            $lat += (rand(-20, 20) / 1000000); // ±20公尺誤差
            $lng += (rand(-20, 20) / 1000000);
            
            // 計算時間
            $pointTime = $startTime->copy()->addMinutes(($durationMinutes * $progress));
            
            // 計算速度 (模擬不同交通工具)
            $speed = $this->calculateSpeed($durationMinutes, $startLocation, $endLocation);
            $speed += rand(-5, 5); // 速度變化
            $speed = max(0, $speed);
            
            $route[] = [
                'lat' => $lat,
                'lng' => $lng,
                'time' => $pointTime,
                'speed' => $speed,
                'accuracy' => rand(3, 15) // GPS精度 3-15公尺
            ];
        }
        
        return $route;
    }

    private function calculateSpeed($durationMinutes, $startLocation, $endLocation)
    {
        // 計算距離 (簡化計算)
        $latDiff = $endLocation['lat'] - $startLocation['lat'];
        $lngDiff = $endLocation['lng'] - $startLocation['lng'];
        $distance = sqrt($latDiff * $latDiff + $lngDiff * $lngDiff) * 111; // 轉換為公里
        
        if ($durationMinutes == 0) return 0;
        
        $speed = ($distance / $durationMinutes) * 60; // km/h
        
        // 根據速度範圍調整為合理值
        if ($speed < 5) return rand(3, 8); // 步行
        if ($speed < 15) return rand(10, 20); // 腳踏車
        if ($speed < 40) return rand(25, 45); // 機車/汽車
        
        return rand(30, 60); // 汽車
    }

    private function formatGpsData($gpsData, $userId)
    {
        return array_map(function ($point) use ($userId) {
            return [
                'user_id' => $userId,
                'latitude' => $point['lat'],
                'longitude' => $point['lng'],
                'recorded_at' => $point['time']->format('Y-m-d H:i:s'),
                'speed' => $point['speed'] ?? 0,
                'accuracy' => $point['accuracy'] ?? 10,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }, $gpsData);
    }
}