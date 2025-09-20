<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GpsTrackSeeder extends Seeder
{
    /**
     * 執行資料庫種子
     */
    public function run(): void
    {
        // 假設用戶ID為1 (請確保你的users表中有ID為1的用戶)
        $userId = 1;
        $startDate = Carbon::now()->subDays(30);
        
        echo "開始生成GPS軌跡測試資料...\n";
        
        for ($day = 0; $day < 30; $day++) {
            $currentDate = $startDate->copy()->addDays($day);
            $isWeekend = $currentDate->isWeekend();
            
            echo "生成 {$currentDate->format('Y-m-d')} 的軌跡資料\n";
            
            if (!$isWeekend) {
                // 平日行程
                $this->generateWeekdayRoutes($userId, $currentDate);
            } else {
                // 週末行程
                $this->generateWeekendRoutes($userId, $currentDate);
            }
        }
        
        echo "GPS軌跡測試資料生成完成！\n";
    }
    
    /**
     * 生成平日路線
     */
    private function generateWeekdayRoutes($userId, $date)
    {
        // 早上通勤 - 從家裡到公司 (可能是捷運+步行)
        $this->generateCommuteRoute($userId, $date, 'morning');
        
        // 中午外出用餐 - 短距離步行
        $this->generateLunchRoute($userId, $date);
        
        // 下午可能的外出 (有30%機率)
        if (rand(1, 100) <= 30) {
            $this->generateAfternoonTrip($userId, $date);
        }
        
        // 晚上下班回家
        $this->generateCommuteRoute($userId, $date, 'evening');
        
        // 晚上可能的活動 (有40%機率)
        if (rand(1, 100) <= 40) {
            $this->generateEveningActivity($userId, $date);
        }
    }
    
    /**
     * 生成週末路線
     */
    private function generateWeekendRoutes($userId, $date)
    {
        // 週末外出活動
        $activities = ['shopping', 'restaurant', 'park', 'friend', 'exercise'];
        $selectedActivity = $activities[array_rand($activities)];
        
        switch ($selectedActivity) {
            case 'shopping':
                $this->generateShoppingTrip($userId, $date);
                break;
            case 'restaurant':
                $this->generateRestaurantTrip($userId, $date);
                break;
            case 'park':
                $this->generateParkTrip($userId, $date);
                break;
            case 'friend':
                $this->generateFriendVisit($userId, $date);
                break;
            case 'exercise':
                $this->generateExerciseRoute($userId, $date);
                break;
        }
    }
    
    /**
     * 生成通勤路線
     */
    private function generateCommuteRoute($userId, $date, $period)
    {
        // 台北市內的模擬通勤路線
        $homeLocation = ['lat' => 25.0330, 'lng' => 121.5654]; // 信義區住家
        $officeLocation = ['lat' => 25.0425, 'lng' => 121.5687]; // 大安區辦公室
        
        if ($period === 'morning') {
            $startTime = '08:' . sprintf('%02d', rand(0, 30));
            $from = $homeLocation;
            $to = $officeLocation;
        } else {
            $startTime = '18:' . sprintf('%02d', rand(30, 59));
            $from = $officeLocation;
            $to = $homeLocation;
        }
        
        // 模擬捷運通勤 (包含步行到捷運站 + 捷運移動 + 步行到目的地)
        $this->generateRoute($userId, $date, [
            ['time' => $startTime, 'lat' => $from['lat'], 'lng' => $from['lng'], 'type' => 'start'],
            ['time' => $this->addMinutes($startTime, 5), 'lat' => $from['lat'] + 0.003, 'lng' => $from['lng'] + 0.002, 'type' => 'walking'],
            ['time' => $this->addMinutes($startTime, 8), 'lat' => $from['lat'] + 0.005, 'lng' => $from['lng'] + 0.003, 'type' => 'mrt_station'],
            ['time' => $this->addMinutes($startTime, 25), 'lat' => $to['lat'] - 0.004, 'lng' => $to['lng'] - 0.002, 'type' => 'mrt_station'],
            ['time' => $this->addMinutes($startTime, 30), 'lat' => $to['lat'], 'lng' => $to['lng'], 'type' => 'end'],
        ]);
    }
    
    /**
     * 生成午餐路線
     */
    private function generateLunchRoute($userId, $date)
    {
        $officeLocation = ['lat' => 25.0425, 'lng' => 121.5687];
        $restaurantLocation = ['lat' => 25.0435, 'lng' => 121.5695];
        
        $startTime = '12:' . sprintf('%02d', rand(0, 30));
        
        // 步行到餐廳 + 用餐 + 步行回辦公室
        $this->generateRoute($userId, $date, [
            ['time' => $startTime, 'lat' => $officeLocation['lat'], 'lng' => $officeLocation['lng'], 'type' => 'start'],
            ['time' => $this->addMinutes($startTime, 3), 'lat' => 25.0430, 'lng' => 121.5690, 'type' => 'walking'],
            ['time' => $this->addMinutes($startTime, 6), 'lat' => $restaurantLocation['lat'], 'lng' => $restaurantLocation['lng'], 'type' => 'restaurant'],
            ['time' => $this->addMinutes($startTime, 35), 'lat' => $restaurantLocation['lat'], 'lng' => $restaurantLocation['lng'], 'type' => 'restaurant'],
            ['time' => $this->addMinutes($startTime, 41), 'lat' => $officeLocation['lat'], 'lng' => $officeLocation['lng'], 'type' => 'end'],
        ]);
    }
    
    /**
     * 生成購物行程
     */
    private function generateShoppingTrip($userId, $date)
    {
        $homeLocation = ['lat' => 25.0330, 'lng' => 121.5654];
        $mallLocation = ['lat' => 25.0520, 'lng' => 121.5430]; // 台北車站商圈
        
        $startTime = '14:' . sprintf('%02d', rand(0, 30));
        
        // 開車或搭車到購物中心
        $this->generateRoute($userId, $date, [
            ['time' => $startTime, 'lat' => $homeLocation['lat'], 'lng' => $homeLocation['lng'], 'type' => 'start'],
            ['time' => $this->addMinutes($startTime, 20), 'lat' => $mallLocation['lat'], 'lng' => $mallLocation['lng'], 'type' => 'shopping'],
            ['time' => $this->addMinutes($startTime, 180), 'lat' => $mallLocation['lat'], 'lng' => $mallLocation['lng'], 'type' => 'shopping'],
            ['time' => $this->addMinutes($startTime, 200), 'lat' => $homeLocation['lat'], 'lng' => $homeLocation['lng'], 'type' => 'end'],
        ]);
    }
    
    /**
     * 生成運動路線 (慢跑或騎腳踏車)
     */
    private function generateExerciseRoute($userId, $date)
    {
        $homeLocation = ['lat' => 25.0330, 'lng' => 121.5654];
        $startTime = '06:' . sprintf('%02d', rand(0, 30));
        
        // 模擬慢跑路線 (環形)
        $route = [
            ['time' => $startTime, 'lat' => $homeLocation['lat'], 'lng' => $homeLocation['lng'], 'type' => 'start'],
        ];
        
        // 生成環形慢跑路線
        for ($i = 1; $i <= 8; $i++) {
            $angle = ($i / 8) * 2 * M_PI;
            $radius = 0.01; // 約1公里半徑
            $lat = $homeLocation['lat'] + $radius * cos($angle);
            $lng = $homeLocation['lng'] + $radius * sin($angle);
            
            $route[] = [
                'time' => $this->addMinutes($startTime, $i * 7),
                'lat' => $lat,
                'lng' => $lng,
                'type' => 'jogging'
            ];
        }
        
        $route[] = ['time' => $this->addMinutes($startTime, 60), 'lat' => $homeLocation['lat'], 'lng' => $homeLocation['lng'], 'type' => 'end'];
        
        $this->generateRoute($userId, $date, $route);
    }
    
    /**
     * 生成路線軌跡點
     */
    private function generateRoute($userId, $date, $points)
    {
        foreach ($points as $point) {
            // 加入隨機變化模擬真實GPS誤差
            $latVariation = (rand(-20, 20) / 1000000); // ±20公尺誤差
            $lngVariation = (rand(-20, 20) / 1000000);
            
            $data = [
                'user_id' => $userId,
                'latitude' => $point['lat'] + $latVariation,
                'longitude' => $point['lng'] + $lngVariation,
                'recorded_at' => $date->format('Y-m-d') . ' ' . $point['time'] . ':' . sprintf('%02d', rand(0, 59)),
                'altitude' => rand(10, 100),
                'speed' => $this->calculateSpeed($point['type']),
                'accuracy' => rand(3, 15),
                'bearing' => rand(0, 360),
                'is_processed' => false,
                'device_type' => 'mobile',
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            DB::table('gps_tracks')->insert($data);
        }
    }
    
    /**
     * 根據移動類型計算合理速度
     */
    private function calculateSpeed($type)
    {
        switch ($type) {
            case 'walking':
                return rand(3, 6);
            case 'jogging':
                return rand(8, 12);
            case 'bicycle':
                return rand(15, 25);
            case 'mrt':
                return rand(35, 55);
            case 'car':
                return rand(20, 60);
            case 'bus':
                return rand(15, 40);
            default:
                return rand(0, 5);
        }
    }
    
    /**
     * 時間加法輔助函數
     */
    private function addMinutes($time, $minutes)
    {
        $timestamp = strtotime($time);
        $newTimestamp = $timestamp + ($minutes * 60);
        return date('H:i', $newTimestamp);
    }
    
    /**
     * 生成下午外出行程
     */
    private function generateAfternoonTrip($userId, $date)
    {
        $officeLocation = ['lat' => 25.0425, 'lng' => 121.5687];
        $meetingLocation = ['lat' => 25.0380, 'lng' => 121.5620];
        
        $startTime = '15:' . sprintf('%02d', rand(0, 30));
        
        // 可能是開會或拜訪客戶
        $this->generateRoute($userId, $date, [
            ['time' => $startTime, 'lat' => $officeLocation['lat'], 'lng' => $officeLocation['lng'], 'type' => 'start'],
            ['time' => $this->addMinutes($startTime, 15), 'lat' => $meetingLocation['lat'], 'lng' => $meetingLocation['lng'], 'type' => 'meeting'],
            ['time' => $this->addMinutes($startTime, 75), 'lat' => $meetingLocation['lat'], 'lng' => $meetingLocation['lng'], 'type' => 'meeting'],
            ['time' => $this->addMinutes($startTime, 90), 'lat' => $officeLocation['lat'], 'lng' => $officeLocation['lng'], 'type' => 'end'],
        ]);
    }
    
    /**
     * 生成晚上活動
     */
    private function generateEveningActivity($userId, $date)
    {
        $homeLocation = ['lat' => 25.0330, 'lng' => 121.5654];
        $activityLocation = ['lat' => 25.0460, 'lng' => 121.5700]; // 可能是餐廳或運動場所
        
        $startTime = '19:' . sprintf('%02d', rand(30, 59));
        
        $this->generateRoute($userId, $date, [
            ['time' => $startTime, 'lat' => $homeLocation['lat'], 'lng' => $homeLocation['lng'], 'type' => 'start'],
            ['time' => $this->addMinutes($startTime, 20), 'lat' => $activityLocation['lat'], 'lng' => $activityLocation['lng'], 'type' => 'activity'],
            ['time' => $this->addMinutes($startTime, 120), 'lat' => $activityLocation['lat'], 'lng' => $activityLocation['lng'], 'type' => 'activity'],
            ['time' => $this->addMinutes($startTime, 140), 'lat' => $homeLocation['lat'], 'lng' => $homeLocation['lng'], 'type' => 'end'],
        ]);
    }
    
    /**
     * 生成餐廳行程
     */
    private function generateRestaurantTrip($userId, $date)
    {
        $homeLocation = ['lat' => 25.0330, 'lng' => 121.5654];
        $restaurantLocation = ['lat' => 25.0400, 'lng' => 121.5500]; // 東區餐廳
        
        $startTime = '18:' . sprintf('%02d', rand(0, 30));
        
        $this->generateRoute($userId, $date, [
            ['time' => $startTime, 'lat' => $homeLocation['lat'], 'lng' => $homeLocation['lng'], 'type' => 'start'],
            ['time' => $this->addMinutes($startTime, 25), 'lat' => $restaurantLocation['lat'], 'lng' => $restaurantLocation['lng'], 'type' => 'restaurant'],
            ['time' => $this->addMinutes($startTime, 105), 'lat' => $restaurantLocation['lat'], 'lng' => $restaurantLocation['lng'], 'type' => 'restaurant'],
            ['time' => $this->addMinutes($startTime, 130), 'lat' => $homeLocation['lat'], 'lng' => $homeLocation['lng'], 'type' => 'end'],
        ]);
    }
    
    /**
     * 生成公園行程
     */
    private function generateParkTrip($userId, $date)
    {
        $homeLocation = ['lat' => 25.0330, 'lng' => 121.5654];
        $parkLocation = ['lat' => 25.0300, 'lng' => 121.5600]; // 大安森林公園
        
        $startTime = '09:' . sprintf('%02d', rand(0, 30));
        
        // 可能是騎腳踏車或步行到公園
        $this->generateRoute($userId, $date, [
            ['time' => $startTime, 'lat' => $homeLocation['lat'], 'lng' => $homeLocation['lng'], 'type' => 'start'],
            ['time' => $this->addMinutes($startTime, 15), 'lat' => $parkLocation['lat'], 'lng' => $parkLocation['lng'], 'type' => 'park'],
            ['time' => $this->addMinutes($startTime, 90), 'lat' => $parkLocation['lat'], 'lng' => $parkLocation['lng'], 'type' => 'park'],
            ['time' => $this->addMinutes($startTime, 105), 'lat' => $homeLocation['lat'], 'lng' => $homeLocation['lng'], 'type' => 'end'],
        ]);
    }
    
    /**
     * 生成拜訪朋友行程
     */
    private function generateFriendVisit($userId, $date)
    {
        $homeLocation = ['lat' => 25.0330, 'lng' => 121.5654];
        $friendLocation = ['lat' => 25.0150, 'lng' => 121.5800]; // 朋友家 (較遠的地方)
        
        $startTime = '13:' . sprintf('%02d', rand(0, 30));
        
        // 可能搭捷運或開車
        $this->generateRoute($userId, $date, [
            ['time' => $startTime, 'lat' => $homeLocation['lat'], 'lng' => $homeLocation['lng'], 'type' => 'start'],
            ['time' => $this->addMinutes($startTime, 35), 'lat' => $friendLocation['lat'], 'lng' => $friendLocation['lng'], 'type' => 'friend'],
            ['time' => $this->addMinutes($startTime, 180), 'lat' => $friendLocation['lat'], 'lng' => $friendLocation['lng'], 'type' => 'friend'],
            ['time' => $this->addMinutes($startTime, 215), 'lat' => $homeLocation['lat'], 'lng' => $homeLocation['lng'], 'type' => 'end'],
        ]);
    }
}
