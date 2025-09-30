<?php

// ====================================
// 1. 更新的 GPS 假資料生成器 (樹德科大到麥當勞)
// database/seeders/GpsFakeDataSeeder.php
// ====================================

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Exception;

class GpsFakeDataSeeder extends Seeder
{
    public function run()
    {
        try {
            $this->checkRequiredTables();
            
            $userId = 2;
            $this->ensureUserExists($userId);
            $this->clearOldData($userId);
            
            // 生成去程資料 (下班：樹德科大到麥當勞)
            $this->generateOutboundTrip($userId);
            
            // 生成回程資料 (上班：麥當勞到樹德科大)
            $this->generateReturnTrip($userId);
            
            echo "✅ GPS假資料生成完成！\n";
            echo "🚴 路線：樹德科技大學 ↔ 麥當勞楠梓餐廳\n";
            echo "📊 已生成 2 筆行程記錄\n";
            echo "📍 GPS資料點數量：" . DB::table('gps_tracks')->where('user_id', $userId)->count() . "\n";
            
        } catch (Exception $e) {
            echo "❌ 錯誤：" . $e->getMessage() . "\n";
        }
    }
    
    private function checkRequiredTables()
    {
        $requiredTables = ['users', 'gps_tracks', 'trips', 'carbon_emissions'];
        $missingTables = [];
        
        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                $missingTables[] = $table;
            }
        }
        
        if (!empty($missingTables)) {
            throw new Exception("缺少以下資料表：" . implode(', ', $missingTables));
        }
    }
    
    private function ensureUserExists($userId)
    {
        $user = DB::table('users')->where('id', $userId)->first();
        
        if (!$user) {
            DB::table('users')->insert([
                'id' => $userId,
                'name' => '測試用戶',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            echo "📝 已創建測試用戶 (ID: {$userId})\n";
        }
    }
    
    private function clearOldData($userId)
    {
        if (Schema::hasTable('carbon_emissions')) {
            DB::table('carbon_emissions')->where('user_id', $userId)->delete();
        }
        
        if (Schema::hasTable('trips')) {
            DB::table('trips')->where('user_id', $userId)->delete();
        }
        
        if (Schema::hasTable('gps_tracks')) {
            DB::table('gps_tracks')->where('user_id', $userId)->delete();
        }
        
        echo "🗑️ 已清除舊資料\n";
    }
    
    /**
     * 生成去程GPS資料 (樹德科大到麥當勞) - 下班
     */
    private function generateOutboundTrip($userId)
    {
        echo "🏫 生成去程資料（樹德科技大學 → 麥當勞楠梓餐廳）...\n";
        
        // 起始時間：晚上11:34
        $startTime = Carbon::today()->setTime(23, 34, 0);
        $totalSeconds = 18 * 60 + 36; // 18分36秒
        
        // 定義路線關鍵點（樹德科大到楠梓麥當勞）
        $keyPoints = [
            // 樹德科技大學
            ['lat' => 22.7632, 'lng' => 120.3757, 'name' => '樹德科技大學'],
            ['lat' => 22.7648, 'lng' => 120.3782, 'name' => '橫山路出口'],
            ['lat' => 22.7671, 'lng' => 120.3815, 'name' => '橫山路段1'],
            ['lat' => 22.7695, 'lng' => 120.3848, 'name' => '橫山路段2'],
            
            // 進入旗楠路
            ['lat' => 22.7718, 'lng' => 120.3881, 'name' => '轉入旗楠路'],
            ['lat' => 22.7742, 'lng' => 120.3914, 'name' => '旗楠路段1'],
            ['lat' => 22.7765, 'lng' => 120.3947, 'name' => '旗楠路段2'],
            ['lat' => 22.7789, 'lng' => 120.3980, 'name' => '旗楠路段3'],
            ['lat' => 22.7812, 'lng' => 120.4013, 'name' => '旗楠路段4'],
            
            // 接近楠梓區
            ['lat' => 22.7836, 'lng' => 120.4046, 'name' => '進入楠梓區'],
            ['lat' => 22.7859, 'lng' => 120.4079, 'name' => '楠梓路段1'],
            ['lat' => 22.7883, 'lng' => 120.4112, 'name' => '楠梓路段2'],
            
            // 建楠路
            ['lat' => 22.7906, 'lng' => 120.4145, 'name' => '轉入建楠路'],
            ['lat' => 22.7930, 'lng' => 120.4178, 'name' => '建楠路段1'],
            ['lat' => 22.7953, 'lng' => 120.4211, 'name' => '建楠路段2'],
            ['lat' => 22.7977, 'lng' => 120.4244, 'name' => '建楠路段3'],
            
            // 麥當勞附近
            ['lat' => 22.8000, 'lng' => 120.4277, 'name' => '接近麥當勞'],
            ['lat' => 22.8024, 'lng' => 120.4310, 'name' => '麥當勞停車場'],
            ['lat' => 22.8047, 'lng' => 120.4343, 'name' => '麥當勞-高雄楠梓餐廳'],
        ];
        
        $gpsData = $this->generateGpsPoints($keyPoints, $startTime, $totalSeconds);
        
        $gpsInsertData = [];
        foreach ($gpsData as $point) {
            $gpsInsertData[] = [
                'user_id' => $userId,
                'latitude' => $point['lat'],
                'longitude' => $point['lng'],
                'altitude' => rand(10, 50),
                'speed' => $point['speed'],
                'accuracy' => rand(5, 15),
                'bearing' => $point['bearing'],
                'recorded_at' => $point['timestamp'],
                'device_type' => 'ESP32',
                'is_processed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('gps_tracks')->insert($gpsInsertData);
        
        $distance = $this->calculateTotalDistance($keyPoints);
        $tripId = DB::table('trips')->insertGetId([
            'user_id' => $userId,
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addSeconds($totalSeconds),
            'start_latitude' => $keyPoints[0]['lat'],
            'start_longitude' => $keyPoints[0]['lng'],
            'end_latitude' => $keyPoints[count($keyPoints) - 1]['lat'],
            'end_longitude' => $keyPoints[count($keyPoints) - 1]['lng'],
            'distance' => $distance,
            'duration' => $totalSeconds,
            'transport_mode' => 'motorcycle',
            'trip_type' => 'from_work',
            'avg_speed' => 35,
            'max_speed' => 60,
            'stop_count' => rand(2, 5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        DB::table('carbon_emissions')->insert([
            'user_id' => $userId,
            'trip_id' => $tripId,
            'date' => $startTime->toDateString(),
            'transport_mode' => 'motorcycle',
            'distance' => $distance,
            'carbon_amount' => $distance * 0.095,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        echo "  ✓ 生成 " . count($gpsData) . " 個GPS點\n";
        echo "  ✓ 距離: " . round($distance, 2) . " 公里\n";
    }
    
    /**
     * 生成回程GPS資料 (麥當勞到樹德科大) - 上班
     */
    private function generateReturnTrip($userId)
    {
        echo "🍟 生成回程資料（麥當勞楠梓餐廳 → 樹德科技大學）...\n";
        
        $startTime = Carbon::tomorrow()->setTime(0, 21, 0);
        $totalSeconds = 19 * 60 + 43;
        
        // 回程路線（反向）
        $keyPoints = array_reverse($this->getOutboundKeyPoints());
        
        $gpsData = $this->generateGpsPoints($keyPoints, $startTime, $totalSeconds);
        
        $gpsInsertData = [];
        foreach ($gpsData as $point) {
            $gpsInsertData[] = [
                'user_id' => $userId,
                'latitude' => $point['lat'],
                'longitude' => $point['lng'],
                'altitude' => rand(10, 50),
                'speed' => $point['speed'],
                'accuracy' => rand(5, 15),
                'bearing' => $point['bearing'],
                'recorded_at' => $point['timestamp'],
                'device_type' => 'ESP32',
                'is_processed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('gps_tracks')->insert($gpsInsertData);
        
        $distance = $this->calculateTotalDistance($keyPoints);
        $tripId = DB::table('trips')->insertGetId([
            'user_id' => $userId,
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addSeconds($totalSeconds),
            'start_latitude' => $keyPoints[0]['lat'],
            'start_longitude' => $keyPoints[0]['lng'],
            'end_latitude' => $keyPoints[count($keyPoints) - 1]['lat'],
            'end_longitude' => $keyPoints[count($keyPoints) - 1]['lng'],
            'distance' => $distance,
            'duration' => $totalSeconds,
            'transport_mode' => 'motorcycle',
            'trip_type' => 'to_work',
            'avg_speed' => 33,
            'max_speed' => 58,
            'stop_count' => rand(3, 6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        DB::table('carbon_emissions')->insert([
            'user_id' => $userId,
            'trip_id' => $tripId,
            'date' => $startTime->toDateString(),
            'transport_mode' => 'motorcycle',
            'distance' => $distance,
            'carbon_amount' => $distance * 0.095,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        echo "  ✓ 生成 " . count($gpsData) . " 個GPS點\n";
        echo "  ✓ 距離: " . round($distance, 2) . " 公里\n";
    }
    
    private function getOutboundKeyPoints()
    {
        return [
            ['lat' => 22.7632, 'lng' => 120.3757, 'name' => '樹德科技大學'],
            ['lat' => 22.7648, 'lng' => 120.3782, 'name' => '橫山路出口'],
            ['lat' => 22.7671, 'lng' => 120.3815, 'name' => '橫山路段1'],
            ['lat' => 22.7695, 'lng' => 120.3848, 'name' => '橫山路段2'],
            ['lat' => 22.7718, 'lng' => 120.3881, 'name' => '轉入旗楠路'],
            ['lat' => 22.7742, 'lng' => 120.3914, 'name' => '旗楠路段1'],
            ['lat' => 22.7765, 'lng' => 120.3947, 'name' => '旗楠路段2'],
            ['lat' => 22.7789, 'lng' => 120.3980, 'name' => '旗楠路段3'],
            ['lat' => 22.7812, 'lng' => 120.4013, 'name' => '旗楠路段4'],
            ['lat' => 22.7836, 'lng' => 120.4046, 'name' => '進入楠梓區'],
            ['lat' => 22.7859, 'lng' => 120.4079, 'name' => '楠梓路段1'],
            ['lat' => 22.7883, 'lng' => 120.4112, 'name' => '楠梓路段2'],
            ['lat' => 22.7906, 'lng' => 120.4145, 'name' => '轉入建楠路'],
            ['lat' => 22.7930, 'lng' => 120.4178, 'name' => '建楠路段1'],
            ['lat' => 22.7953, 'lng' => 120.4211, 'name' => '建楠路段2'],
            ['lat' => 22.7977, 'lng' => 120.4244, 'name' => '建楠路段3'],
            ['lat' => 22.8000, 'lng' => 120.4277, 'name' => '接近麥當勞'],
            ['lat' => 22.8024, 'lng' => 120.4310, 'name' => '麥當勞停車場'],
            ['lat' => 22.8047, 'lng' => 120.4343, 'name' => '麥當勞-高雄楠梓餐廳'],
        ];
    }
    
    private function generateGpsPoints($keyPoints, $startTime, $totalSeconds)
    {
        $gpsData = [];
        $currentTime = $startTime->copy();
        $interval = 5;
        $totalPoints = ceil($totalSeconds / $interval);
        $pointsPerSegment = floor($totalPoints / (count($keyPoints) - 1));
        
        $pointIndex = 0;
        for ($i = 0; $i < count($keyPoints) - 1; $i++) {
            $startPoint = $keyPoints[$i];
            $endPoint = $keyPoints[$i + 1];
            
            $segmentPoints = ($i == count($keyPoints) - 2) 
                ? ($totalPoints - $pointIndex) 
                : $pointsPerSegment;
            
            for ($j = 0; $j < $segmentPoints; $j++) {
                $progress = $j / max($segmentPoints, 1);
                
                $lat = $startPoint['lat'] + ($endPoint['lat'] - $startPoint['lat']) * $progress;
                $lng = $startPoint['lng'] + ($endPoint['lng'] - $startPoint['lng']) * $progress;
                
                $baseSpeed = 35;
                $speedVariation = sin($pointIndex * 0.1) * 15;
                $speed = max(20, min(60, $baseSpeed + $speedVariation));
                
                if (rand(1, 100) <= 5) {
                    $speed = 0;
                }
                
                $bearing = $this->calculateBearing(
                    $startPoint['lat'], $startPoint['lng'],
                    $endPoint['lat'], $endPoint['lng']
                );
                
                $gpsData[] = [
                    'lat' => round($lat, 8),
                    'lng' => round($lng, 8),
                    'speed' => round($speed, 2),
                    'bearing' => round($bearing, 2),
                    'timestamp' => $currentTime->toDateTimeString(),
                ];
                
                $currentTime->addSeconds($interval);
                $pointIndex++;
            }
        }
        
        return $gpsData;
    }
    
    private function calculateTotalDistance($points)
    {
        $totalDistance = 0;
        
        for ($i = 0; $i < count($points) - 1; $i++) {
            $distance = $this->calculateDistance(
                $points[$i]['lat'], $points[$i]['lng'],
                $points[$i + 1]['lat'], $points[$i + 1]['lng']
            );
            $totalDistance += $distance;
        }
        
        return round($totalDistance, 2);
    }
    
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371;
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
    
    private function calculateBearing($lat1, $lon1, $lat2, $lon2)
    {
        $dLon = deg2rad($lon2 - $lon1);
        $lat1 = deg2rad($lat1);
        $lat2 = deg2rad($lat2);
        
        $y = sin($dLon) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLon);
        
        $bearing = rad2deg(atan2($y, $x));
        
        return ($bearing + 360) % 360;
    }
}