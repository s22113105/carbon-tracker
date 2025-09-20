<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\GeofenceController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use Illuminate\Support\Facades\Auth;

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
    Route::put('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.update-role');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::post('/users/bulk-action', [AdminUserController::class, 'bulkAction'])->name('users.bulk-action');
    
    // 全公司統計
    Route::get('/statistics', [StatisticsController::class, 'index'])->name('statistics');
    Route::get('/statistics/export', [StatisticsController::class, 'exportData'])->name('statistics.export');
    Route::get('/statistics/export-advanced', [StatisticsController::class, 'exportAdvancedReport'])->name('statistics.export-advanced');
    
    // 地理圍欄設定
    Route::get('/geofence', [GeofenceController::class, 'index'])->name('geofence');
    Route::post('/geofence', [GeofenceController::class, 'store'])->name('geofence.store');
    Route::put('/geofence/{geofence}', [GeofenceController::class, 'update'])->name('geofence.update');
    Route::delete('/geofence/{geofence}', [GeofenceController::class, 'destroy'])->name('geofence.destroy');
    Route::post('/geofence/{geofence}/toggle', [GeofenceController::class, 'toggle'])->name('geofence.toggle');
    Route::post('/geofence/check', [GeofenceController::class, 'checkGeofence'])->name('geofence.check');
    Route::get('/geofence/data', [GeofenceController::class, 'getGeofenceData'])->name('geofence.data');
    Route::get('/geofence/statistics', [GeofenceController::class, 'getStatistics'])->name('geofence.statistics');
    Route::post('/geofence/bulk-action', [GeofenceController::class, 'bulkAction'])->name('geofence.bulk-action');
    
    // 系統設定
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/clear-cache', [SettingsController::class, 'clearCache'])->name('settings.clear-cache');
    Route::post('/settings/optimize', [SettingsController::class, 'optimizeSystem'])->name('settings.optimize');
    Route::post('/settings/backup', [SettingsController::class, 'backupDatabase'])->name('settings.backup');
    Route::post('/settings/test-email', [SettingsController::class, 'testEmail'])->name('settings.test-email');
});

// ===== 一般使用者路由群組 =====
Route::middleware(['auth'])->prefix('user')->name('user.')->group(function () {
    
    // 使用者儀表板
    Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard');
    
    // 其他使用者功能...
    // Route::get('/charts', [UserChartsController::class, 'index'])->name('charts');
    // Route::get('/map', [UserMapController::class, 'index'])->name('map');
    // Route::get('/attendance', [UserAttendanceController::class, 'index'])->name('attendance');
    // Route::get('/realtime', [UserRealtimeController::class, 'index'])->name('realtime');
    // Route::get('/ai-suggestions', [UserAISuggestionsController::class, 'index'])->name('ai-suggestions');
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
    // Route::apiResource('geofences', App\Http\Controllers\Api\GeofenceController::class);
    // Route::post('/geofences/check', [App\Http\Controllers\Api\GeofenceController::class, 'checkLocation'])->name('geofences.check');
    // Route::get('/geofences/{id}/statistics', [App\Http\Controllers\Api\GeofenceController::class, 'statistics'])->name('geofences.statistics');
    
    // GPS 記錄 API
    // Route::apiResource('gps-records', App\Http\Controllers\Api\GpsRecordController::class);
    // Route::post('/gps-records/batch', [App\Http\Controllers\Api\GpsRecordController::class, 'storeBatch'])->name('gps-records.batch');
    
    // // 行程 API
    // Route::apiResource('trips', App\Http\Controllers\Api\TripController::class);
    // Route::post('/trips/{id}/complete', [App\Http\Controllers\Api\TripController::class, 'complete'])->name('trips.complete');
    // Route::get('/trips/{id}/statistics', [App\Http\Controllers\Api\TripController::class, 'statistics'])->name('trips.statistics');
    
    // // 碳排放 API
    // Route::apiResource('carbon-emissions', App\Http\Controllers\Api\CarbonEmissionController::class);
    // Route::get('/carbon-emissions/statistics/monthly', [App\Http\Controllers\Api\CarbonEmissionController::class, 'monthlyStatistics'])->name('carbon-emissions.monthly-stats');
    // Route::get('/carbon-emissions/statistics/transport', [App\Http\Controllers\Api\CarbonEmissionController::class, 'transportStatistics'])->name('carbon-emissions.transport-stats');
});

// ===== 公開 API 路由（不需要認證）=====
Route::prefix('api/public')->name('api.public.')->group(function () {
    
    // 系統狀態檢查
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0')
        ]);
    })->name('health');
    
    // 交通工具列表
    Route::get('/transport-modes', function () {
        return response()->json([
            'transport_modes' => [
                'walking' => '步行',
                'bicycle' => '腳踏車',
                'motorcycle' => '機車',
                'car' => '汽車',
                'bus' => '公車',
                'train' => '火車',
                'metro' => '捷運',
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
                'train' => 0.041, // kg CO2 per km
                'metro' => 0.033, // kg CO2 per km
                'other' => 0.15 // kg CO2 per km (平均值)
            ]
        ]);
    })->name('emission-factors');
});

// ===== 測試路由（僅在開發環境）=====
if (app()->environment('local')) {
    Route::prefix('test')->name('test.')->group(function () {
        
        // 地理圍欄測試
        Route::get('/geofence', function () {
            return view('test.geofence');
        })->name('geofence');
        
        // GPS 模擬器
        Route::get('/gps-simulator', function () {
            return view('test.gps-simulator');
        })->name('gps-simulator');
        
        // 報表預覽
        Route::get('/report-preview', function () {
            return view('test.report-preview');
        })->name('report-preview');
    });
}
