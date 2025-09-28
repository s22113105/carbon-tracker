<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GpsController;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Sanctum 認證路由（保留給網頁使用）
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// ESP32 專用路由 (不需要認證)
Route::prefix('esp32')->group(function () {
    // GPS 資料接收
    Route::post('/gps', [GpsController::class, 'store']);
    Route::post('/gps/batch', [GpsController::class, 'storeBatch']);
    Route::get('/gps/test', [GpsController::class, 'test']);
    
    // 設備狀態檢查
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString()
        ]);
    });
});

Route::post('/gps', [App\Http\Controllers\Api\GpsController::class, 'store']);

// 受保護的 API 路由（需要認證）
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/gps/latest', [GpsController::class, 'getLatest']);
    Route::get('/gps/by-date-range', [GpsController::class, 'getByDateRange']);
});

use App\Http\Controllers\Api\TripAnalysisController;

// 需要認證的行程分析API路由
Route::middleware('auth:sanctum')->prefix('trips')->name('api.trips.')->group(function () {
    
    // 手動分析指定日期的GPS資料
    Route::post('analyze-date', [TripAnalysisController::class, 'analyzeDate'])
         ->name('analyze.date');
    
    // 批量分析日期範圍的GPS資料
    Route::post('analyze-range', [TripAnalysisController::class, 'analyzeDateRange'])
         ->name('analyze.range');
    
    // 獲取行程的GPS軌跡
    Route::get('{tripId}/trace', [TripAnalysisController::class, 'getTripTrace'])
         ->name('trace')
         ->where('tripId', '[0-9]+');
    
    // 獲取分析統計資料
    Route::get('stats', [TripAnalysisController::class, 'getAnalysisStats'])
         ->name('stats');
    
    // 刪除指定日期的行程資料
    Route::delete('date', [TripAnalysisController::class, 'deleteTripsForDate'])
         ->name('delete.date');
});

// 管理員專用的批量分析路由（如果需要）
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin/trips')->name('api.admin.trips.')->group(function () {
    
    // 分析所有用戶的指定日期資料
    Route::post('analyze-all-users', function (Request $request) {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|before_or_equal:today',
            'force' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '參數驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $date = $request->input('date');
            $force = $request->boolean('force', false);
            
            // 執行 Artisan 命令
            $exitCode = Artisan::call('gps:analyze-trips', [
                '--date' => $date,
                '--force' => $force
            ]);
            
            $output = Artisan::output();
            
            return response()->json([
                'success' => $exitCode === 0,
                'message' => $exitCode === 0 ? '批量分析完成' : '批量分析失敗',
                'data' => [
                    'date' => $date,
                    'force' => $force,
                    'output' => $output,
                    'exit_code' => $exitCode
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '執行失敗: ' . $e->getMessage()
            ], 500);
        }
    })->name('analyze.all');
    
    // 獲取系統整體統計
    Route::get('system-stats', function (Request $request) {
        try {
            $validator = Validator::make($request->all(), [
                'days' => 'integer|min:1|max:365'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '參數驗證失敗',
                    'errors' => $validator->errors()
                ], 422);
            }

            $days = $request->input('days', 30);
            $startDate = Carbon::now()->subDays($days);

            // 系統統計
            $stats = [
                'users_with_gps' => \App\Models\User::whereHas('gpsRecords')->count(),
                'total_gps_records' => \App\Models\GpsRecord::where('recorded_at', '>=', $startDate)->count(),
                'total_trips' => \App\Models\Trip::where('start_time', '>=', $startDate)->count(),
                'total_distance' => \App\Models\Trip::where('start_time', '>=', $startDate)->sum('distance'),
                'avg_trips_per_user' => 0,
                'top_transport_modes' => [],
                'daily_activity' => []
            ];

            // 平均每用戶行程數
            if ($stats['users_with_gps'] > 0) {
                $stats['avg_trips_per_user'] = round($stats['total_trips'] / $stats['users_with_gps'], 2);
            }

            // 熱門交通工具
            $stats['top_transport_modes'] = \App\Models\Trip::where('start_time', '>=', $startDate)
                ->selectRaw('transport_mode, COUNT(*) as count, SUM(distance) as total_distance')
                ->groupBy('transport_mode')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get();

            // 每日活動統計
            $stats['daily_activity'] = \App\Models\Trip::where('start_time', '>=', $startDate)
                ->selectRaw('DATE(start_time) as date, COUNT(*) as trips, COUNT(DISTINCT user_id) as active_users')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->limit(30)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'period_days' => $days,
                    'start_date' => $startDate->format('Y-m-d'),
                    'stats' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '獲取系統統計失敗: ' . $e->getMessage()
            ], 500);
        }
    })->name('system.stats');
});

// 公開的API端點（用於ESP32設備等）
Route::prefix('public/trips')->name('api.public.trips.')->group(function () {
    
    // 檢查是否需要分析（供ESP32等設備呼叫）
    Route::post('check-analysis-needed', function (Request $request) {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string',
            'user_id' => 'required|integer|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '參數驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = $request->input('user_id');
            $yesterday = Carbon::yesterday()->format('Y-m-d');
            
            // 檢查昨天是否有GPS資料但沒有行程記錄
            $hasGpsData = \App\Models\GpsRecord::where('user_id', $userId)
                ->whereDate('recorded_at', $yesterday)
                ->exists();
                
            $hasTrips = \App\Models\Trip::where('user_id', $userId)
                ->whereDate('start_time', $yesterday)
                ->exists();
            
            $needsAnalysis = $hasGpsData && !$hasTrips;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'date' => $yesterday,
                    'has_gps_data' => $hasGpsData,
                    'has_trips' => $hasTrips,
                    'needs_analysis' => $needsAnalysis,
                    'recommendation' => $needsAnalysis ? '建議執行行程分析' : '無需分析'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '檢查失敗: ' . $e->getMessage()
            ], 500);
        }
    })->name('check.analysis');
});

// 測試路由（僅在開發環境）
if (app()->environment('local', 'development')) {
    Route::prefix('test/trips')->name('api.test.trips.')->group(function () {
        
        // 生成測試GPS資料
        Route::post('generate-test-data', function (Request $request) {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id',
                'date' => 'required|date',
                'trip_type' => 'required|in:commute,shopping,random'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '參數驗證失敗',
                    'errors' => $validator->errors()
                ], 422);
            }

            try {
                // 這裡可以調用GpsTrackSeeder的邏輯生成測試資料
                // 實際實現會根據trip_type生成不同類型的GPS軌跡
                
                return response()->json([
                    'success' => true,
                    'message' => '測試資料生成完成',
                    'data' => [
                        'user_id' => $request->input('user_id'),
                        'date' => $request->input('date'),
                        'trip_type' => $request->input('trip_type')
                    ]
                ]);

            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => '生成測試資料失敗: ' . $e->getMessage()
                ], 500);
            }
        })->name('generate.test');
    });
}