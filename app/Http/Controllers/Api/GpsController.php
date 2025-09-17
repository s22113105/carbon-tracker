<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GpsRecord;
use App\Models\Trip;
use App\Models\CarbonEmission;
use App\Models\User;
use App\Services\TransportAnalysisService;
use Carbon\Carbon;

class GpsController extends Controller
{
    protected $analysisService;
    
    public function __construct(TransportAnalysisService $analysisService)
    {
        $this->analysisService = $analysisService;
    }
    
    public function store(Request $request)
    {
        try {
            // 記錄原始請求用於調試
            \Log::info('ESP32 GPS Data Received:', $request->all());
            
            // 驗證 ESP32 送來的資料格式
            $validated = $request->validate([
                'device_id' => 'required|string',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'speed' => 'nullable|numeric|min:0',
                'timestamp' => 'nullable|string',
                'battery_level' => 'nullable|integer|between:0,100',
            ]);

            // 根據 device_id 映射到使用者（您可以建立設備-使用者映射表）
            $user = $this->getUserByDeviceId($validated['device_id']);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => '設備未註冊或找不到對應使用者'
                ], 404);
            }

            // 處理時間戳
            $recordedAt = now();
            if (isset($validated['timestamp']) && $validated['timestamp'] !== '') {
                try {
                    // ESP32 傳送的時間格式：2024-1-1 12:0:0
                    $recordedAt = Carbon::createFromFormat('Y-n-j G:i:s', $validated['timestamp']);
                } catch (\Exception $e) {
                    // 如果解析失敗，使用當前時間
                    $recordedAt = now();
                }
            }

            // 儲存 GPS 記錄
            $gpsRecord = GpsRecord::create([
                'user_id' => $user->id,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'recorded_at' => $recordedAt,
                'accuracy' => null, // ESP32 未提供
                'speed' => $validated['speed'] ?? 0,
            ]);

            // 分析行程（智慧判斷是否開始/結束行程）
            $tripInfo = $this->analyzeTrip($user->id, $gpsRecord);

            // 記錄電池電量（可以加入設備狀態表）
            if (isset($validated['battery_level'])) {
                $this->updateDeviceStatus($validated['device_id'], $validated['battery_level']);
            }

            return response()->json([
                'success' => true,
                'message' => 'GPS 資料已成功儲存',
                'data' => [
                    'id' => $gpsRecord->id,
                    'device_id' => $validated['device_id'],
                    'recorded_at' => $gpsRecord->recorded_at->toISOString(),
                    'battery_level' => $validated['battery_level'] ?? null,
                    'trip_info' => $tripInfo,
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('GPS API Validation Error:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => '資料驗證失敗',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            \Log::error('GPS API Error:', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => '儲存資料時發生錯誤',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getUserByDeviceId($deviceId)
    {
        // 暫時使用固定映射，您可以建立 device_users 表來管理
        $deviceUserMapping = [
            'ESP32_CARBON_001' => 'user@example.com',
            // 可以加入更多設備映射
        ];

        if (isset($deviceUserMapping[$deviceId])) {
            return User::where('email', $deviceUserMapping[$deviceId])->first();
        }

        return null;
    }

    private function updateDeviceStatus($deviceId, $batteryLevel)
    {
        // 可以建立 device_status 表來記錄設備狀態
        // 暫時記錄到 log
        \Log::info("Device Status Update: {$deviceId} - Battery: {$batteryLevel}%");
    }

    private function analyzeTrip($userId, $gpsRecord)
    {
        // 檢查是否有進行中的行程
        $activeTrip = Trip::where('user_id', $userId)
            ->whereNull('end_time')
            ->latest('start_time')
            ->first();

        if ($activeTrip) {
            return $this->checkTripEnd($activeTrip, $gpsRecord);
        } else {
            return $this->checkTripStart($userId, $gpsRecord);
        }
    }

    private function checkTripStart($userId, $gpsRecord)
    {
        // 檢查最近的 GPS 記錄
        $lastRecord = GpsRecord::where('user_id', $userId)
            ->where('id', '!=', $gpsRecord->id)
            ->latest('recorded_at')
            ->first();

        if ($lastRecord) {
            $distance = $this->calculateDistance(
                $lastRecord->latitude, 
                $lastRecord->longitude,
                $gpsRecord->latitude, 
                $gpsRecord->longitude
            );

            // 如果移動距離超過 100 公尺，開始新行程
            if ($distance > 0.1) {
                $trip = Trip::create([
                    'user_id' => $userId,
                    'start_time' => $gpsRecord->recorded_at,
                    'start_latitude' => $gpsRecord->latitude,
                    'start_longitude' => $gpsRecord->longitude,
                    'transport_mode' => 'unknown',
                    'trip_type' => $this->determineTripType($gpsRecord),
                ]);

                return [
                    'action' => 'trip_started',
                    'trip_id' => $trip->id,
                    'distance_from_last' => $distance
                ];
            }
        }

        return ['action' => 'no_trip_change'];
    }

    private function checkTripEnd($trip, $gpsRecord)
    {
        $timeDiff = $gpsRecord->recorded_at->diffInMinutes($trip->start_time);
        
        // 如果行程時間超過 5 分鐘，考慮結束行程
        if ($timeDiff > 5) {
            $distance = $this->calculateDistance(
                $trip->start_latitude,
                $trip->start_longitude,
                $gpsRecord->latitude,
                $gpsRecord->longitude
            );

            // 結束行程
            $trip->update([
                'end_time' => $gpsRecord->recorded_at,
                'end_latitude' => $gpsRecord->latitude,
                'end_longitude' => $gpsRecord->longitude,
                'distance' => $distance,
            ]);

            // 分析交通工具和碳排放
            $this->analyzeAndSaveCarbonEmission($trip);

            return [
                'action' => 'trip_ended',
                'trip_id' => $trip->id,
                'distance' => $distance,
                'duration' => $timeDiff
            ];
        }

        return [
            'action' => 'trip_continuing',
            'trip_id' => $trip->id,
            'duration' => $timeDiff
        ];
    }

    private function determineTripType($gpsRecord)
    {
        // 簡單的時間判斷邏輯
        $hour = $gpsRecord->recorded_at->hour;
        
        if ($hour >= 7 && $hour <= 10) {
            return 'to_work';
        } elseif ($hour >= 17 && $hour <= 20) {
            return 'from_work';
        }
        
        return 'other';
    }

    private function analyzeAndSaveCarbonEmission($trip)
    {
        $duration = $trip->start_time->diffInMinutes($trip->end_time);
        
        if ($duration > 0 && $trip->distance > 0) {
            $analysis = $this->analysisService->analyzeTransport($trip->distance, $duration);
            
            $trip->update(['transport_mode' => $analysis['transport_mode']]);
            
            CarbonEmission::create([
                'user_id' => $trip->user_id,
                'trip_id' => $trip->id,
                'emission_date' => $trip->start_time->toDateString(),
                'transport_mode' => $analysis['transport_mode'],
                'distance' => $trip->distance,
                'co2_emission' => $analysis['co2_emission'],
            ]);
        }
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // 地球半徑 (公里)
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }

    public function test()
    {
        return response()->json([
            'success' => true,
            'message' => 'GPS API 正常運作',
            'server_time' => now()->toISOString(),
            'endpoints' => [
                'gps_data' => url('/api/gps'),
                'test' => url('/api/gps/test')
            ]
        ]);
    }
}