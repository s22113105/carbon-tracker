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
     * 接收ESP32的GPS資料
     */
    public function store(Request $request)
    {
        try {
            // 記錄接收到的資料
            Log::info('ESP32 GPS Data Received:', $request->all());
            
            // 基本資料驗證
            $validated = $request->validate([
                'device_id' => 'required|string',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'speed' => 'nullable|numeric|min:0',
                'timestamp' => 'nullable|string',
                'battery_level' => 'nullable|integer|between:0,100',
            ]);

            // 設備ID映射到用戶ID（您可以根據需要調整）
            $userId = $this->getUserIdFromDevice($validated['device_id']);
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的用戶'
                ], 404);
            }

            // 處理時間戳
            $recordedAt = $this->parseTimestamp($validated['timestamp'] ?? null);

            // 儲存到 gps_tracks 表（根據您的migration結構）
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

            // 記錄設備狀態（電池電量等）
            if (isset($validated['battery_level'])) {
                $this->logDeviceStatus($validated['device_id'], $validated['battery_level']);
            }

            return response()->json([
                'success' => true,
                'message' => 'GPS資料已成功儲存',
                'data' => [
                    'id' => $gpsTrackId,
                    'device_id' => $validated['device_id'],
                    'user_id' => $userId,
                    'recorded_at' => $recordedAt,
                    'battery_level' => $validated['battery_level'] ?? null,
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('GPS API Validation Error:', $e->errors());
            return response()->json([
                'success' => false,
                'message' => '資料格式錯誤',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('GPS API Error:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
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
        Log::info('GPS Test Endpoint Called:', $request->all());
        
        return response()->json([
            'success' => true,
            'message' => 'GPS測試端點正常運作',
            'received_data' => $request->all(),
            'server_time' => now()->toISOString(),
        ]);
    }

    /**
     * 根據設備ID獲取用戶ID
     */
    private function getUserIdFromDevice($deviceId)
    {
        // 設備與用戶的映射關係
        $deviceUserMapping = [
            'ESP32_CARBON_001' => 1, // 假設用戶ID為1
            'ESP32_CARBON_002' => 2,
            // 可以加入更多設備映射
        ];

        // 如果有專門的設備管理表，可以查詢資料庫
        // return DB::table('device_users')->where('device_id', $deviceId)->value('user_id');

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
            // 嘗試解析ESP32傳送的時間格式
            if (is_numeric($timestamp)) {
                // 如果是Unix時間戳或毫秒
                return Carbon::createFromTimestamp($timestamp);
            }
            
            // 嘗試解析字串格式
            return Carbon::parse($timestamp);
            
        } catch (\Exception $e) {
            Log::warning('Failed to parse timestamp: ' . $timestamp, ['error' => $e->getMessage()]);
            return now();
        }
    }

    /**
     * 記錄設備狀態
     */
    private function logDeviceStatus($deviceId, $batteryLevel)
    {
        try {
            // 可以建立專門的設備狀態表
            DB::table('device_status')->updateOrInsert(
                ['device_id' => $deviceId],
                [
                    'battery_level' => $batteryLevel,
                    'last_seen' => now(),
                    'updated_at' => now(),
                ]
            );
        } catch (\Exception $e) {
            // 如果設備狀態表不存在，只記錄到日誌
            Log::info("Device Status: {$deviceId} - Battery: {$batteryLevel}%");
        }
    }

    /**
     * 獲取最新的GPS記錄
     */
    public function getLatest(Request $request)
    {
        $userId = $request->input('user_id', 1);
        
        $latestRecord = DB::table('gps_tracks')
            ->where('user_id', $userId)
            ->orderBy('recorded_at', 'desc')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $latestRecord
        ]);
    }

    /**
     * 根據日期範圍獲取GPS記錄
     */
    public function getByDateRange(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $records = DB::table('gps_tracks')
            ->where('user_id', $validated['user_id'])
            ->whereBetween('recorded_at', [
                $validated['start_date'] . ' 00:00:00',
                $validated['end_date'] . ' 23:59:59'
            ])
            ->orderBy('recorded_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $records,
            'count' => $records->count()
        ]);
    }

    /**
     * 批次儲存GPS資料
     */
    public function storeBatch(Request $request)
    {
        try {
            $validated = $request->validate([
                'device_id' => 'required|string',
                'gps_data' => 'required|array',
                'gps_data.*.latitude' => 'required|numeric|between:-90,90',
                'gps_data.*.longitude' => 'required|numeric|between:-180,180',
                'gps_data.*.speed' => 'nullable|numeric|min:0',
                'gps_data.*.timestamp' => 'nullable|string',
            ]);

            $userId = $this->getUserIdFromDevice($validated['device_id']);
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到對應的用戶'
                ], 404);
            }

            $insertData = [];
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
            }

            DB::table('gps_tracks')->insert($insertData);

            Log::info("Batch GPS data saved: {$validated['device_id']}, " . count($insertData) . " records");

            return response()->json([
                'success' => true,
                'message' => '批次GPS資料已成功儲存',
                'count' => count($insertData)
            ]);

        } catch (\Exception $e) {
            Log::error('Batch GPS API Error:', ['message' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => '批次儲存資料時發生錯誤'
            ], 500);
        }
    }
}