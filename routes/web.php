<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use App\Http\Controllers\User\ChartController;
use App\Http\Controllers\User\MapController;
use App\Http\Controllers\User\AttendanceController;
use App\Http\Controllers\User\AiController;
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
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');        // 管理員總覽儀表板
    Route::get('/users', [App\Http\Controllers\Admin\UserController::class, 'index'])->name('users'); // 使用者管理與權限設定
});

// ===== 一般使用者路由群組 =====
Route::middleware(['auth'])->prefix('user')->name('user.')->group(function () {
    Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard');          // 個人儀表板
    Route::get('/charts', [ChartController::class, 'index'])->name('charts');                        // 碳排放統計圖表
    Route::get('/map', [MapController::class, 'index'])->name('map');                                // 地圖顯示通勤路線
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance');          // 打卡記錄管理
    Route::get('/ai-suggestions', [AiController::class, 'index'])->name('ai-suggestions');          // AI 減碳建議
    Route::get('/realtime', [RealTimeController::class, 'index'])->name('realtime');                // 即時 GPS 監控
});

// ===== 開發測試路由 =====
Route::get('/test-gps', function () {
    return view('test-gps-api');
})->middleware('auth')->name('test.gps');                                                            // GPS API 測試頁面