<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GpsController extends Controller
{
    /**
     * 接收ESP32的GPS資料（公開端點）
     */
    public function store(Request $request)
    {
        try {
            // 記錄所有接收到的資料用於除錯
            Log::info('ESP32 GPS Data Received:', [
                'headers' => $request->headers->all(),
                'body' => $request->all(),
                'ip' => $request->ip(),
                'method' => $request->method()
            ]);
            
            // 基本資料驗證（放寬限制）
            $validated = $request->validate([
                'device_id' => 'required|string|max:50',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'speed' => 'nullable|numeric|min:0',
                'timestamp' => 'nullable',
                'battery_level' => 'nullable|integer|between:0,100',
            ]);

            // 設備ID映射到用戶ID
            $userId = $this->getUserIdFromDevice($validated['device_id']);
            
            if (!$userId) {
                // 如果找不到對應用戶，使用預設用戶ID = 1
                $userId = 1;
                Log::warning("Device {$validated['device_id']} not mapped, using default user_id=1");
            }

            // 處理時間戳
            $recordedAt = $this->parseTimestamp($validated['timestamp'] ?? null);

            // 使用事務處理確保資料一致性
            DB::beginTransaction();
            
            try {
                // 儲存到 gps_tracks 表
                $gpsTrackId = DB::table('gps_tracks')->insertGetId([
                    'user_id' => $userId,
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'speed' => $validated['speed'] ?? 0,
                    'recorded_at' => $recordedAt,
                    'device_type' => 'ESP32',
                    'is_processed' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 更新設備狀態
                $this->updateDeviceStatus($validated['device_id'], [
                    'battery_level' => $validated['battery_level'] ?? null,
                    'last_seen' => now(),
                    'is_online' => true,
                    'ip_address' => $request->ip()
                ]);

                DB::commit();

                Log::info("GPS data saved successfully", [
                    'track_id' => $gpsTrackId,
                    'device_id' => $validated['device_id']
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'GPS資料已成功儲存',
                    'data' => [
                        'id' => $gpsTrackId,
                        'device_id' => $validated['device_id'],
                        'user_id' => $userId,
                        'recorded_at' => $recordedAt->toISOString(),
                    ]
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('GPS API Validation Error:', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '資料格式錯誤',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('GPS API Error:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '儲存資料時發生錯誤',
                'error' => config('app.debug') ? $e->getMessage() : '內部伺服器錯誤'
            ], 500);
        }
    }

    /**
     * 測試端點
     */
    public function test(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'GPS測試端點正常運作',
            'server_time' => now()->toISOString(),
            'your_ip' => $request->ip(),
            'test_data' => [
                'device_id' => 'ESP32_CARBON_001',
                'latitude' => 25.033,
                'longitude' => 121.565,
                'speed' => 15.5,
                'battery_level' => 85
            ]
        ]);
    }

    /**
     * 根據設備ID獲取用戶ID
     */
    private function getUserIdFromDevice($deviceId)
    {
        // 先嘗試從資料庫查找
        $mapping = DB::table('device_users')
            ->where('device_id', $deviceId)
            ->first();
        
        if ($mapping) {
            return $mapping->user_id;
        }
        
        // 使用硬編碼映射作為後備
        $deviceUserMapping = [
            'ESP32_CARBON_001' => 1,
            'ESP32_CARBON_002' => 2,
            'ESP32_TEST' => 1,
        ];

        return $deviceUserMapping[$deviceId] ?? null;
    }

    /**
     * 解析時間戳
     */
    private function parseTimestamp($timestamp)
    {
        if (empty($timestamp)) {
            return now();
        }

        try {
            if (is_numeric($timestamp)) {
                // Unix 時間戳
                if ($timestamp > 9999999999) {
                    // 毫秒時間戳
                    return Carbon::createFromTimestampMs($timestamp);
                }
                return Carbon::createFromTimestamp($timestamp);
            }
            
            // 嘗試解析字串格式
            return Carbon::parse($timestamp);
            
        } catch (\Exception $e) {
            Log::warning('Failed to parse timestamp', [
                'timestamp' => $timestamp,
                'error' => $e->getMessage()
            ]);
            return now();
        }
    }

    /**
     * 更新設備狀態
     */
    private function updateDeviceStatus($deviceId, array $data)
    {
        try {
            DB::table('device_status')->updateOrInsert(
                ['device_id' => $deviceId],
                array_merge($data, ['updated_at' => now()])
            );
        } catch (\Exception $e) {
            Log::warning('Failed to update device status', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 批次儲存GPS資料
     */
    public function storeBatch(Request $request)
    {
        try {
            $validated = $request->validate([
                'device_id' => 'required|string',
                'gps_data' => 'required|array|min:1|max:100',
                'gps_data.*.latitude' => 'required|numeric|between:-90,90',
                'gps_data.*.longitude' => 'required|numeric|between:-180,180',
                'gps_data.*.speed' => 'nullable|numeric|min:0',
                'gps_data.*.timestamp' => 'nullable',
                'gps_data.*.battery_level' => 'nullable|integer|between:0,100',
            ]);

            $userId = $this->getUserIdFromDevice($validated['device_id']) ?? 1;
            
            DB::beginTransaction();
            
            try {
                $insertData = [];
                $lastBatteryLevel = null;
                
                foreach ($validated['gps_data'] as $gpsRecord) {
                    $insertData[] = [
                        'user_id' => $userId,
                        'latitude' => $gpsRecord['latitude'],
                        'longitude' => $gpsRecord['longitude'],
                        'speed' => $gpsRecord['speed'] ?? 0,
                        'recorded_at' => $this->parseTimestamp($gpsRecord['timestamp'] ?? null),
                        'device_type' => 'ESP32',
                        'is_processed' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    
                    if (isset($gpsRecord['battery_level'])) {
                        $lastBatteryLevel = $gpsRecord['battery_level'];
                    }
                }

                DB::table('gps_tracks')->insert($insertData);
                
                // 更新設備狀態
                $this->updateDeviceStatus($validated['device_id'], [
                    'battery_level' => $lastBatteryLevel,
                    'last_seen' => now(),
                    'is_online' => true,
                    'ip_address' => $request->ip()
                ]);

                DB::commit();

                Log::info("Batch GPS data saved", [
                    'device_id' => $validated['device_id'],
                    'count' => count($insertData)
                ]);

                return response()->json([
                    'success' => true,
                    'message' => '批次GPS資料已成功儲存',
                    'count' => count($insertData)
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Batch GPS API Error:', [
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '批次儲存資料時發生錯誤'
            ], 500);
        }
    }
}