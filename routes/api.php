<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GpsController;

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