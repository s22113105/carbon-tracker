<?php

namespace App\Services;

use App\Models\GpsRecord;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TripAnalysisService
{
    /**
     * GPS資料時間間隔閾值（分鐘）
     * 超過此時間間隔則視為不同行程
     */
    const TIME_GAP_THRESHOLD = 5;
    
    /**
     * 最小移動距離（公尺）
     * 小於此距離視為停留
     */
    const MIN_MOVEMENT_DISTANCE = 50;
    
    /**
     * 最小行程持續時間（分鐘）
     */
    const MIN_TRIP_DURATION = 3;

    /**
     * 分析指定日期的GPS資料並生成行程
     *
     * @param int $userId
     * @param string $date 格式：Y-m-d
     * @return array
     */
    public function analyzeTripsForDate($userId, $date)
    {
        Log::info("開始分析GPS資料", ['user_id' => $userId, 'date' => $date]);
        
        // 獲取指定日期的GPS資料
        $gpsData = $this->getGpsDataForDate($userId, $date);
        
        if ($gpsData->isEmpty()) {
            Log::info("沒有GPS資料可分析", ['user_id' => $userId, 'date' => $date]);
            return [];
        }
        
        // 按時間間隔分組GPS資料
        $tripSegments = $this->segmentGpsDataByTimeGaps($gpsData);
        
        // 為每個時間段生成行程記錄
        $trips = [];
        foreach ($tripSegments as $segment) {
            $trip = $this->createTripFromSegment($userId, $segment);
            if ($trip) {
                $trips[] = $trip;
            }
        }
        
        Log::info("GPS資料分析完成", [
            'user_id' => $userId, 
            'date' => $date, 
            'trips_created' => count($trips)
        ]);
        
        return $trips;
    }

    /**
     * 獲取指定日期的GPS資料
     */
    private function getGpsDataForDate($userId, $date)
    {
        return GpsRecord::where('user_id', $userId)
            ->whereDate('recorded_at', $date)
            ->orderBy('recorded_at', 'asc')
            ->get();
    }

    /**
     * 根據時間間隔分組GPS資料
     */
    private function segmentGpsDataByTimeGaps($gpsData)
    {
        $segments = [];
        $currentSegment = [];
        $lastTimestamp = null;
        
        foreach ($gpsData as $gpsPoint) {
            $currentTimestamp = Carbon::parse($gpsPoint->recorded_at);
            
            // 如果是第一個點或時間間隔小於閾值，加入當前段
            if ($lastTimestamp === null || 
                $currentTimestamp->diffInMinutes($lastTimestamp) <= self::TIME_GAP_THRESHOLD) {
                $currentSegment[] = $gpsPoint;
            } else {
                // 時間間隔超過閾值，開始新的段
                if (!empty($currentSegment)) {
                    $segments[] = $currentSegment;
                }
                $currentSegment = [$gpsPoint];
            }
            
            $lastTimestamp = $currentTimestamp;
        }
        
        // 加入最後一段
        if (!empty($currentSegment)) {
            $segments[] = $currentSegment;
        }
        
        return $segments;
    }

    /**
     * 從GPS段創建行程記錄
     */
    private function createTripFromSegment($userId, $segment)
    {
        if (count($segment) < 2) {
            return null; // 至少需要2個GPS點
        }
        
        $startPoint = $segment[0];
        $endPoint = end($segment);
        
        $startTime = Carbon::parse($startPoint->recorded_at);
        $endTime = Carbon::parse($endPoint->recorded_at);
        
        // 檢查行程持續時間
        if ($endTime->diffInMinutes($startTime) < self::MIN_TRIP_DURATION) {
            return null;
        }
        
        // 計算總距離
        $totalDistance = $this->calculateTotalDistance($segment);
        
        // 檢查是否有實際移動
        if ($totalDistance < self::MIN_MOVEMENT_DISTANCE / 1000) { // 轉換為公里
            return null;
        }
        
        // 判斷行程類型
        $tripType = $this->determineTripType($startTime, $endTime);
        
        // 估算交通方式
        $transportMode = $this->estimateTransportMode($segment, $totalDistance, $endTime->diffInMinutes($startTime));
        
        // 創建行程記錄
        $trip = Trip::create([
            'user_id' => $userId,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'start_latitude' => $startPoint->latitude,
            'start_longitude' => $startPoint->longitude,
            'end_latitude' => $endPoint->latitude,
            'end_longitude' => $endPoint->longitude,
            'distance' => $totalDistance,
            'transport_mode' => $transportMode,
            'trip_type' => $tripType,
        ]);
        
        Log::info("創建行程記錄", [
            'trip_id' => $trip->id,
            'duration_minutes' => $endTime->diffInMinutes($startTime),
            'distance_km' => $totalDistance,
            'transport_mode' => $transportMode
        ]);
        
        return $trip;
    }

    /**
     * 計算GPS段的總距離
     */
    private function calculateTotalDistance($segment)
    {
        $totalDistance = 0;
        
        for ($i = 1; $i < count($segment); $i++) {
            $prevPoint = $segment[$i - 1];
            $currPoint = $segment[$i];
            
            $distance = $this->calculateDistance(
                $prevPoint->latitude,
                $prevPoint->longitude,
                $currPoint->latitude,
                $currPoint->longitude
            );
            
            $totalDistance += $distance;
        }
        
        return $totalDistance;
    }

    /**
     * 使用Haversine公式計算兩點間距離（公里）
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // 地球半徑（公里）
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }

    /**
     * 根據時間判斷行程類型
     */
    private function determineTripType($startTime, $endTime)
    {
        $hour = $startTime->hour;
        
        // 早上通勤時間 (6:00-10:00)
        if ($hour >= 6 && $hour <= 10) {
            return 'to_work';
        }
        
        // 下班時間 (16:00-20:00)
        if ($hour >= 16 && $hour <= 20) {
            return 'from_work';
        }
        
        return 'other';
    }

    /**
     * 根據GPS資料估算交通方式
     */
    private function estimateTransportMode($segment, $distance, $durationMinutes)
    {
        if ($durationMinutes == 0) {
            return 'unknown';
        }
        
        // 計算平均速度 (km/h)
        $avgSpeed = ($distance / $durationMinutes) * 60;
        
        // 根據速度範圍估算交通方式
        if ($avgSpeed <= 6) {
            return 'walking';
        } elseif ($avgSpeed <= 15) {
            return 'bicycle';
        } elseif ($avgSpeed <= 35) {
            return 'motorcycle';
        } elseif ($avgSpeed <= 80) {
            return 'car';
        } else {
            return 'mrt'; // 可能是捷運或其他快速交通工具
        }
    }

    /**
     * 獲取行程的詳細GPS軌跡
     */
    public function getTripGpsTrace($tripId)
    {
        $trip = Trip::findOrFail($tripId);
        
        return GpsRecord::where('user_id', $trip->user_id)
            ->whereBetween('recorded_at', [$trip->start_time, $trip->end_time])
            ->orderBy('recorded_at', 'asc')
            ->select('latitude as lat', 'longitude as lng', 'recorded_at', 'speed', 'accuracy')
            ->get()
            ->map(function ($point) {
                return [
                    'lat' => (float) $point->lat,
                    'lng' => (float) $point->lng,
                    'time' => $point->recorded_at,
                    'speed' => $point->speed,
                    'accuracy' => $point->accuracy
                ];
            });
    }

    /**
     * 重新分析指定日期的所有行程
     */
    public function reanalyzeTripsForDate($userId, $date)
    {
        // 刪除該日期的現有行程記錄
        Trip::where('user_id', $userId)
            ->whereDate('start_time', $date)
            ->delete();
        
        // 重新分析
        return $this->analyzeTripsForDate($userId, $date);
    }

    /**
     * 批量分析多個日期的GPS資料
     */
    public function batchAnalyzeTrips($userId, $startDate, $endDate)
    {
        $currentDate = Carbon::parse($startDate);
        $endDate = Carbon::parse($endDate);
        $results = [];
        
        while ($currentDate->lte($endDate)) {
            $dateString = $currentDate->format('Y-m-d');
            $trips = $this->analyzeTripsForDate($userId, $dateString);
            $results[$dateString] = count($trips);
            
            $currentDate->addDay();
        }
        
        return $results;
    }
}