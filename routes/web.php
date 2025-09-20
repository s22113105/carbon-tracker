<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;

// Home Controller
use App\Http\Controllers\HomeController;

// Admin Controllers
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\GeofenceController as AdminGeofenceController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;

// User Controllers
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use App\Http\Controllers\User\ChartController;
use App\Http\Controllers\User\MapController;
use App\Http\Controllers\User\AttendanceController;
use App\Http\Controllers\User\RealtimeController;
use App\Http\Controllers\User\AiSuggestionController;

// API Controllers
use App\Http\Controllers\Api\UserController as ApiUserController;
use App\Http\Controllers\Api\GeofenceController as ApiGeofenceController;
use App\Http\Controllers\Api\GpsController as ApiGpsController;
use App\Http\Controllers\Api\TripController as ApiTripController;
use App\Http\Controllers\Api\CarbonEmissionController as ApiCarbonEmissionController;
use App\Http\Controllers\Api\DeviceController as ApiDeviceController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// ===== 首頁路由 =====
Route::get('/', [HomeController::class, 'index'])->name('home.index');

// ===== 認證路由 =====
Auth::routes();

// ===== 登入後根據角色重導向 =====
Route::get('/home', function () {
    if (auth()->user()->isAdmin()) {
        return redirect('/admin/dashboard');
    }
    return redirect('/user/dashboard');
})->middleware('auth')->name('home');

// ===== 管理員路由群組 =====
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    
    // 管理員儀表板
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    
    // 使用者管理
    Route::get('/users', [AdminUserController::class, 'index'])->name('users');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::put('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.update-role');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::post('/users/bulk-action', [AdminUserController::class, 'bulkAction'])->name('users.bulk-action');
    Route::post('/users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::post('/users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('users.reset-password');
    
    // 全公司統計
    Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics');
    Route::get('/statistics/export/{type}', [StatisticsController::class, 'exportData'])->name('statistics.export');
    Route::get('/statistics/export-advanced', [StatisticsController::class, 'exportAdvancedReport'])->name('statistics.export-advanced');
    
    // 地理圍欄設定
    Route::get('/geofence', [AdminGeofenceController::class, 'index'])->name('geofence');
    Route::post('/geofence', [AdminGeofenceController::class, 'store'])->name('geofence.store');
    Route::put('/geofence/{geofence}', [AdminGeofenceController::class, 'update'])->name('geofence.update');
    Route::delete('/geofence/{geofence}', [AdminGeofenceController::class, 'destroy'])->name('geofence.destroy');
    Route::post('/geofence/{geofence}/toggle', [AdminGeofenceController::class, 'toggle'])->name('geofence.toggle');
    Route::post('/geofence/check', [AdminGeofenceController::class, 'checkGeofence'])->name('geofence.check');
    Route::get('/geofence/data', [AdminGeofenceController::class, 'getGeofenceData'])->name('geofence.data');
    Route::get('/geofence/statistics', [AdminGeofenceController::class, 'getStatistics'])->name('geofence.statistics');
    Route::post('/geofence/bulk-action', [AdminGeofenceController::class, 'bulkAction'])->name('geofence.bulk-action');
    Route::post('/geofence/import', [AdminGeofenceController::class, 'import'])->name('geofence.import');
    Route::get('/geofence/export', [AdminGeofenceController::class, 'export'])->name('geofence.export');
    
    // 系統設定
    Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings');
    Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/clear-cache', [AdminSettingsController::class, 'clearCache'])->name('settings.clear-cache');
    Route::post('/settings/optimize', [AdminSettingsController::class, 'optimizeSystem'])->name('settings.optimize');
    Route::post('/settings/backup', [AdminSettingsController::class, 'backupDatabase'])->name('settings.backup');
    Route::post('/settings/test-email', [AdminSettingsController::class, 'testEmail'])->name('settings.test-email');
    Route::post('/settings/update-ai-config', [AdminSettingsController::class, 'updateAiConfig'])->name('settings.update-ai-config');
    Route::post('/settings/test-ai-connection', [AdminSettingsController::class, 'testAiConnection'])->name('settings.test-ai-connection');
    Route::get('/settings/system-info', [AdminSettingsController::class, 'getSystemInfo'])->name('settings.system-info');
    Route::get('/settings/logs', [AdminSettingsController::class, 'getLogs'])->name('settings.logs');
    Route::delete('/settings/clear-logs', [AdminSettingsController::class, 'clearLogs'])->name('settings.clear-logs');
});

// ===== 一般使用者路由群組 =====
Route::middleware(['auth'])->prefix('user')->name('user.')->group(function () {
    
    // 使用者儀表板
    Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/overview', [UserDashboardController::class, 'getOverview'])->name('dashboard.overview');
    Route::get('/dashboard/recent-trips', [UserDashboardController::class, 'getRecentTrips'])->name('dashboard.recent-trips');
    Route::get('/dashboard/carbon-summary', [UserDashboardController::class, 'getCarbonSummary'])->name('dashboard.carbon-summary');
    Route::get('/dashboard/weekly-stats', [UserDashboardController::class, 'getWeeklyStats'])->name('dashboard.weekly-stats');
    
    // 圖表頁面
    Route::get('/charts', [ChartController::class, 'index'])->name('charts');
    Route::get('/charts/carbon-trends', [ChartController::class, 'getCarbonTrends'])->name('charts.carbon-trends');
    Route::get('/charts/transport-distribution', [ChartController::class, 'getTransportDistribution'])->name('charts.transport-distribution');
    Route::get('/charts/monthly-comparison', [ChartController::class, 'getMonthlyComparison'])->name('charts.monthly-comparison');
    Route::get('/charts/daily-patterns', [ChartController::class, 'getDailyPatterns'])->name('charts.daily-patterns');
    Route::get('/charts/distance-analysis', [ChartController::class, 'getDistanceAnalysis'])->name('charts.distance-analysis');
    
    // 地圖頁面
    Route::get('/map', [MapController::class, 'index'])->name('map');
    Route::get('/map/trips', [MapController::class, 'getTrips'])->name('map.trips');
    Route::get('/map/realtime-location', [MapController::class, 'getRealtimeLocation'])->name('map.realtime-location');
    Route::get('/map/geofences', [MapController::class, 'getGeofences'])->name('map.geofences');
    Route::get('/map/heatmap-data', [MapController::class, 'getHeatmapData'])->name('map.heatmap-data');
    Route::post('/map/save-route', [MapController::class, 'saveRoute'])->name('map.save-route');
    
    // 打卡記錄頁面
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance');
    Route::get('/attendance/records', [AttendanceController::class, 'getRecords'])->name('attendance.records');
    Route::get('/attendance/monthly-summary', [AttendanceController::class, 'getMonthlySummary'])->name('attendance.monthly-summary');
    Route::post('/attendance/manual-checkin', [AttendanceController::class, 'manualCheckin'])->name('attendance.manual-checkin');
    Route::post('/attendance/manual-checkout', [AttendanceController::class, 'manualCheckout'])->name('attendance.manual-checkout');
    Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
    
    // 即時監控頁面
    Route::get('/realtime', [RealtimeController::class, 'index'])->name('realtime');
    Route::get('/realtime/gps-data', [RealtimeController::class, 'getGpsData'])->name('realtime.gps-data');
    Route::get('/realtime/current-location', [RealtimeController::class, 'getCurrentLocation'])->name('realtime.current-location');
    Route::get('/realtime/active-trips', [RealtimeController::class, 'getActiveTrips'])->name('realtime.active-trips');
    Route::post('/realtime/start-tracking', [RealtimeController::class, 'startTracking'])->name('realtime.start-tracking');
    Route::post('/realtime/stop-tracking', [RealtimeController::class, 'stopTracking'])->name('realtime.stop-tracking');
    
    // 個人設定
    Route::get('/profile', [UserDashboardController::class, 'profile'])->name('profile');
    Route::post('/profile/update', [UserDashboardController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/change-password', [UserDashboardController::class, 'changePassword'])->name('profile.change-password');
    Route::post('/profile/update-preferences', [UserDashboardController::class, 'updatePreferences'])->name('profile.update-preferences');
    
    // 報表功能
    Route::get('/reports', [UserDashboardController::class, 'reports'])->name('reports');
    Route::get('/reports/carbon-footprint', [UserDashboardController::class, 'getCarbonFootprintReport'])->name('reports.carbon-footprint');
    Route::get('/reports/transport-analysis', [UserDashboardController::class, 'getTransportAnalysisReport'])->name('reports.transport-analysis');
    Route::get('/reports/monthly-summary', [UserDashboardController::class, 'getMonthlySummaryReport'])->name('reports.monthly-summary');
    Route::post('/reports/generate-pdf', [UserDashboardController::class, 'generatePdfReport'])->name('reports.generate-pdf');
});

// ===== API 路由群組 =====
Route::middleware(['auth:sanctum'])->prefix('api')->name('api.')->group(function () {
    
    // 使用者 API
    Route::apiResource('users', App\Http\Controllers\Api\UserController::class);
    Route::get('/users/{id}/statistics', [App\Http\Controllers\Api\UserController::class, 'statistics'])->name('users.statistics');
    Route::get('/users/search', [App\Http\Controllers\Api\UserController::class, 'search'])->name('users.search');
    Route::post('/users/bulk-action', [App\Http\Controllers\Api\UserController::class, 'bulkAction'])->name('users.bulk-action');
    Route::post('/users/{id}/change-password', [App\Http\Controllers\Api\UserController::class, 'changePassword'])->name('users.change-password');
    
    // 地理圍欄 API
    Route::apiResource('geofences', App\Http\Controllers\Api\GeofenceController::class);
    Route::post('/geofences/check', [App\Http\Controllers\Api\GeofenceController::class, 'checkLocation'])->name('geofences.check');
    Route::get('/geofences/{id}/statistics', [App\Http\Controllers\Api\GeofenceController::class, 'statistics'])->name('geofences.statistics');
    
    // GPS 記錄 API
    Route::apiResource('gps-records', App\Http\Controllers\Api\GpsController::class);
    Route::post('/gps-records/batch', [App\Http\Controllers\Api\GpsController::class, 'storeBatch'])->name('gps-records.batch');
    Route::get('/gps-records/latest', [App\Http\Controllers\Api\GpsController::class, 'getLatest'])->name('gps-records.latest');
    Route::get('/gps-records/by-date-range', [App\Http\Controllers\Api\GpsController::class, 'getByDateRange'])->name('gps-records.by-date-range');
    
    // 行程 API
    Route::apiResource('trips', App\Http\Controllers\Api\TripController::class);
    Route::post('/trips/{id}/complete', [App\Http\Controllers\Api\TripController::class, 'complete'])->name('trips.complete');
    Route::get('/trips/{id}/statistics', [App\Http\Controllers\Api\TripController::class, 'statistics'])->name('trips.statistics');
    Route::post('/trips/analyze-transport', [App\Http\Controllers\Api\TripController::class, 'analyzeTransport'])->name('trips.analyze-transport');
    
    // 碳排放 API
    Route::apiResource('carbon-emissions', App\Http\Controllers\Api\CarbonEmissionController::class);
    Route::get('/carbon-emissions/statistics/monthly', [App\Http\Controllers\Api\CarbonEmissionController::class, 'monthlyStatistics'])->name('carbon-emissions.monthly-stats');
    Route::get('/carbon-emissions/statistics/transport', [App\Http\Controllers\Api\CarbonEmissionController::class, 'transportStatistics'])->name('carbon-emissions.transport-stats');
    Route::get('/carbon-emissions/trends', [App\Http\Controllers\Api\CarbonEmissionController::class, 'getTrends'])->name('carbon-emissions.trends');
    Route::get('/carbon-emissions/comparison', [App\Http\Controllers\Api\CarbonEmissionController::class, 'getComparison'])->name('carbon-emissions.comparison');
});

// ===== 公開 API 路由（不需要認證，ESP32 使用）=====
Route::prefix('api/public')->name('api.public.')->group(function () {
    
    // GPS 資料接收端點
    Route::post('/gps', [ApiGpsController::class, 'storePublic'])->name('gps.store');
    Route::post('/gps/batch', [ApiGpsController::class, 'storeBatchPublic'])->name('gps.batch');
    
    // 系統狀態檢查
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'server_time' => now()->format('Y-m-d H:i:s'),
            'timezone' => config('app.timezone', 'UTC')
        ]);
    })->name('health');
    
    // 設備認證
    Route::post('/device/auth', [ApiDeviceController::class, 'authenticate'])->name('device.auth');
    Route::post('/device/register', [ApiDeviceController::class, 'register'])->name('device.register');
    Route::get('/device/config', [ApiDeviceController::class, 'getConfig'])->name('device.config');
    
    // 交通工具列表
    Route::get('/transport-modes', function () {
        return response()->json([
            'transport_modes' => [
                'walking' => '步行',
                'bicycle' => '腳踏車',
                'motorcycle' => '機車',
                'car' => '汽車',
                'bus' => '公車',
                'mrt' => '捷運',
                'train' => '火車',
                'other' => '其他'
            ]
        ]);
    })->name('transport-modes');
    
    // 碳排放係數
    Route::get('/emission-factors', function () {
        return response()->json([
            'emission_factors' => [
                'walking' => 0,
                'bicycle' => 0,
                'motorcycle' => 0.095, // kg CO2 per km
                'car' => 0.21, // kg CO2 per km
                'bus' => 0.089, // kg CO2 per km
                'mrt' => 0.033, // kg CO2 per km
                'train' => 0.041, // kg CO2 per km
                'other' => 0.15 // kg CO2 per km (平均值)
            ]
        ]);
    })->name('emission-factors');
    
    // 地理圍欄檢查
    Route::post('/geofence/check', [ApiGeofenceController::class, 'checkPublic'])->name('geofence.check');
    Route::get('/geofence/list', [ApiGeofenceController::class, 'getPublicList'])->name('geofence.list');
    
    // 時間同步
    Route::get('/time', function () {
        return response()->json([
            'timestamp' => now()->timestamp,
            'iso_string' => now()->toISOString(),
            'formatted' => now()->format('Y-m-d H:i:s'),
            'timezone' => config('app.timezone', 'UTC')
        ]);
    })->name('time');
});

// ===== 開發/測試路由（僅在開發環境）=====
if (app()->environment('local', 'development')) {
    Route::prefix('dev')->name('dev.')->group(function () {
        
        // 資料庫種子測試
        Route::get('/seed-test-data', function () {
            Artisan::call('db:seed', ['--class' => 'TestDataSeeder']);
            return response()->json(['message' => '測試資料已建立']);
        })->name('seed-test-data');
        
        // GPS 模擬器
        Route::get('/gps-simulator', function () {
            return view('dev.gps-simulator');
        })->name('gps-simulator');
        
        // 地理圍欄測試
        Route::get('/geofence-test', function () {
            return view('dev.geofence-test');
        })->name('geofence-test');
        
        // AI 功能測試
        Route::get('/ai-test', function () {
            return view('dev.ai-test');
        })->name('ai-test');
        
        // 報表預覽
        Route::get('/report-preview', function () {
            return view('dev.report-preview');
        })->name('report-preview');
        
        // 清除快取
        Route::get('/clear-cache', function () {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            return response()->json(['message' => '快取已清除']);
        })->name('clear-cache');
        
        // 系統資訊
        Route::get('/system-info', function () {
            return response()->json([
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'database_connection' => config('database.default'),
                'queue_driver' => config('queue.default'),
                'cache_driver' => config('cache.default'),
                'session_driver' => config('session.driver'),
            ]);
        })->name('system-info');
    });
}

// ===== 錯誤處理路由 =====
Route::fallback(function () {
    if (request()->expectsJson()) {
        return response()->json([
            'error' => 'API endpoint not found',
            'message' => 'The requested API endpoint does not exist.'
        ], 404);
    }
    
    return view('errors.404');
});