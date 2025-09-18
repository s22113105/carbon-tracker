<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Admin\GeofenceController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use App\Http\Controllers\User\ChartController;
use App\Http\Controllers\User\MapController;
use App\Http\Controllers\User\AttendanceController;
use App\Http\Controllers\User\AiSuggestionController;
use App\Http\Controllers\User\RealTimeController;

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
    Route::get('/users', [App\Http\Controllers\Admin\UserController::class, 'index'])->name('users');
    Route::put('/users/{user}/role', [App\Http\Controllers\Admin\UserController::class, 'updateRole'])->name('users.update-role');
    Route::delete('/users/{user}', [App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('users.destroy');
    Route::get('/users/{user}', [App\Http\Controllers\Admin\UserController::class, 'show'])->name('users.show');
    Route::post('/users/bulk-action', [App\Http\Controllers\Admin\UserController::class, 'bulkAction'])->name('users.bulk-action');
    
    // 全公司統計
    Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics');
    Route::get('/statistics/export', [StatisticsController::class, 'exportData'])->name('statistics.export');
    
    // 地理圍欄設定
    Route::get('/geofence', [GeofenceController::class, 'index'])->name('geofence');
    Route::post('/geofence', [GeofenceController::class, 'store'])->name('geofence.store');
    Route::put('/geofence/{geofence}', [GeofenceController::class, 'update'])->name('geofence.update');
    Route::delete('/geofence/{geofence}', [GeofenceController::class, 'destroy'])->name('geofence.destroy');
    Route::post('/geofence/{geofence}/toggle', [GeofenceController::class, 'toggle'])->name('geofence.toggle');
    Route::post('/geofence/check', [GeofenceController::class, 'checkGeofence'])->name('geofence.check');
    Route::get('/geofence/data', [GeofenceController::class, 'getGeofenceData'])->name('geofence.data');
    
    // 系統設定
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/clear-cache', [SettingsController::class, 'clearCache'])->name('settings.clear-cache');
    Route::post('/settings/optimize', [SettingsController::class, 'optimizeSystem'])->name('settings.optimize');
    Route::post('/settings/backup', [SettingsController::class, 'backupDatabase'])->name('settings.backup');
    Route::post('/settings/test-email', [SettingsController::class, 'testEmail'])->name('settings.test-email');
    
    // 報表匯出 (額外的報表功能)
    Route::get('/reports', [App\Http\Controllers\Admin\ReportController::class, 'index'])->name('reports');
    Route::post('/reports/generate', [App\Http\Controllers\Admin\ReportController::class, 'generate'])->name('reports.generate');
    Route::get('/reports/download/{id}', [App\Http\Controllers\Admin\ReportController::class, 'download'])->name('reports.download');
    
});

// ===== 一般使用者路由群組 =====
Route::middleware(['auth'])->prefix('user')->name('user.')->group(function () {
    Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard');          // 個人儀表板
    Route::get('/charts', [ChartController::class, 'index'])->name('charts');                        // 碳排放統計圖表
    Route::get('/map', [MapController::class, 'index'])->name('map');                                // 地圖顯示通勤路線
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance');          // 打卡記錄管理
    Route::get('/realtime', [RealTimeController::class, 'index'])->name('realtime');                // 即時 GPS 監控
});

// ===== API 路由 (供 ESP32 或前端使用) =====
Route::middleware(['auth:sanctum'])->prefix('api')->group(function () {
    
    // GPS 資料相關
    Route::post('/gps/record', [App\Http\Controllers\Api\GpsController::class, 'store']);
    Route::get('/gps/latest', [App\Http\Controllers\Api\GpsController::class, 'latest']);
    
    // 地理圍欄檢查
    Route::post('/geofence/check', [GeofenceController::class, 'checkGeofence']);
    Route::get('/geofence/list', [GeofenceController::class, 'getGeofenceData']);
    
    // 碳排放計算
    Route::post('/carbon/calculate', [App\Http\Controllers\Api\CarbonController::class, 'calculate']);
    Route::get('/carbon/statistics', [App\Http\Controllers\Api\CarbonController::class, 'statistics']);
    
    // 行程資料
    Route::post('/trip/start', [App\Http\Controllers\Api\TripController::class, 'start']);
    Route::post('/trip/end', [App\Http\Controllers\Api\TripController::class, 'end']);
    Route::get('/trip/current', [App\Http\Controllers\Api\TripController::class, 'current']);
    
    // 使用者資料
    Route::get('/user/profile', [App\Http\Controllers\Api\UserController::class, 'profile']);
    Route::put('/user/profile', [App\Http\Controllers\Api\UserController::class, 'updateProfile']);
    
});

// ===== 開發測試路由 =====
Route::get('/test-gps', function () {
    return view('test-gps-api');
})->middleware('auth')->name('test.gps');                                                            // GPS API 測試頁面

// AI 建議相關路由 (需要登入)
Route::middleware(['auth'])->group(function () {
    
    // AI 建議頁面
    Route::get('/user/ai-suggestions', [AiSuggestionController::class, 'index'])
        ->name('user.ai-suggestions');
    
    // 生成 AI 建議
    Route::post('/user/ai-suggestions/generate', [AiSuggestionController::class, 'getSuggestions'])
        ->name('user.ai-suggestions.generate');
    
    // 分析 GPS 資料
    Route::post('/user/ai-suggestions/analyze-gps', [AiSuggestionController::class, 'analyzeGpsData'])
        ->name('user.ai-suggestions.analyze');
    
    // 重新分析所有行程
    Route::post('/user/ai-suggestions/reanalyze', [AiSuggestionController::class, 'reanalyzeAllTrips'])
        ->name('user.ai-suggestions.reanalyze');
    
});

// ===== 公開 API 路由 (無需認證) =====
Route::prefix('public-api')->group(function () {
    
    // 系統狀態檢查
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0')
        ]);
    });
    
    // 基本系統資訊
    Route::get('/info', function () {
        return response()->json([
            'app_name' => config('app.name'),
            'api_version' => '1.0',
            'endpoints' => [
                'gps' => '/api/gps/*',
                'carbon' => '/api/carbon/*',
                'geofence' => '/api/geofence/*'
            ]
        ]);
    });
    
});

// ===== 錯誤處理路由 =====
Route::fallback(function () {
    return response()->view('errors.404', [], 404);
});