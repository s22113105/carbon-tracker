<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

use App\Http\Controllers\Api\GpsController;

Route::middleware('api')->group(function () {
    Route::post('/gps', [GpsController::class, 'store']);
    Route::get('/gps/test', [GpsController::class, 'test']); // 測試用
});

Route::post('/gps', [App\Http\Controllers\Api\GpsController::class, 'store']);

// routes/api.php
Route::post('/gps', function(Request $request) {
    // 先簡單儲存GPS資料，暫不處理複雜關聯
    DB::table('gps_records')->insert([
        'device_id' => $request->device_id,
        'latitude' => $request->latitude,
        'longitude' => $request->longitude,
        'speed' => $request->speed,
        'timestamp' => $request->timestamp,
        'battery_level' => $request->battery_level,
        'created_at' => now()
    ]);
    
    return response()->json(['success' => true]);
});