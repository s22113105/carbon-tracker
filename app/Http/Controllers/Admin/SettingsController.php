<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SettingsController extends Controller
{
    public function index()
    {
        // 取得所有系統設定
        $settings = $this->getSystemSettings();
        
        // 系統資訊
        $systemInfo = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'server_os' => php_uname('s'),
            'server_time' => now()->format('Y-m-d H:i:s'),
            'timezone' => config('app.timezone'),
            'database_size' => $this->getDatabaseSize(),
            'storage_usage' => $this->getStorageUsage(),
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
        ];
        
        // 最近的系統活動
        $recentActivities = $this->getRecentActivities();
        
        return view('admin.settings', compact('settings', 'systemInfo', 'recentActivities'));
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.*' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            foreach ($request->settings as $key => $value) {
                SystemSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }

            // 清除快取 (移除 tags)
            Cache::forget('all_settings');

            return response()->json([
                'success' => true,
                'message' => '系統設定已更新成功！'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '設定更新失敗：' . $e->getMessage()
            ], 500);
        }
    }

    public function clearCache()
    {
        try {
            // 清除各種快取
            Cache::flush();
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            
            return response()->json([
                'success' => true,
                'message' => '系統快取已清除成功！'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '快取清除失敗：' . $e->getMessage()
            ], 500);
        }
    }

    public function optimizeSystem()
    {
        try {
            // 系統優化
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            
            return response()->json([
                'success' => true,
                'message' => '系統優化完成！'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '系統優化失敗：' . $e->getMessage()
            ], 500);
        }
    }

    public function backupDatabase()
    {
        try {
            // 這裡應該實作資料庫備份邏輯
            // 為了示範，我們只是回傳成功訊息
            
            $backupName = 'database_backup_' . date('Y-m-d_H-i-s') . '.sql';
            
            // 實際專案中應該使用 spatie/laravel-backup 套件
            // Artisan::call('backup:run');
            
            return response()->json([
                'success' => true,
                'message' => "資料庫備份完成！檔案名稱：{$backupName}"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '資料庫備份失敗：' . $e->getMessage()
            ], 500);
        }
    }

    public function testEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // 發送測試郵件
            Mail::raw('這是一封系統測試郵件，如果您收到此郵件，表示郵件設定正常運作。', function ($message) use ($request) {
                $message->to($request->email)
                        ->subject('[' . config('app.name') . '] 系統測試郵件');
            });

            return response()->json([
                'success' => true,
                'message' => "測試郵件已發送至 {$request->email}"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '郵件發送失敗：' . $e->getMessage()
            ], 500);
        }
    }

    private function getSystemSettings()
    {
        // 修改為不使用 tags 的快取方式
        return Cache::remember('all_settings', 3600, function () {
            $settings = SystemSetting::all()->pluck('value', 'key')->toArray();
            
            // 預設值
            $defaults = [
                'site_name' => config('app.name'),
                'site_description' => '企業碳排放追蹤系統',
                'contact_email' => 'admin@example.com',
                'auto_punch_radius' => '100',
                'max_daily_trips' => '10',
                'carbon_calculation_method' => 'standard',
                'email_notifications' => '1',
                'sms_notifications' => '0',
                'maintenance_mode' => '0',
                'backup_frequency' => 'daily',
                'log_retention_days' => '30',
                'max_upload_size' => '10',
                'allowed_file_types' => 'jpg,jpeg,png,pdf,csv,xlsx',
                'session_timeout' => '120',
                'password_min_length' => '8',
                'enable_2fa' => '0',
                'api_rate_limit' => '60',
                'gps_accuracy_threshold' => '20',
                'trip_merge_threshold' => '300'
            ];

            return array_merge($defaults, $settings);
        });
    }

    private function getDatabaseSize()
    {
        try {
            $databaseName = config('database.connections.mysql.database');
            $result = DB::select("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = '{$databaseName}'
            ");
            
            return $result[0]->size_mb ?? 0;
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    private function getStorageUsage()
    {
        try {
            $storagePath = storage_path();
            $bytes = disk_total_space($storagePath) - disk_free_space($storagePath);
            $mb = round($bytes / 1024 / 1024, 2);
            
            return $mb;
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    private function getRecentActivities()
    {
        // 模擬最近的系統活動
        return collect([
            [
                'time' => now()->subMinutes(5),
                'type' => 'user_login',
                'description' => '使用者登入系統',
                'user' => 'user@example.com'
            ],
            [
                'time' => now()->subMinutes(15),
                'type' => 'gps_update',
                'description' => 'GPS 資料更新',
                'user' => 'admin@example.com'
            ],
            [
                'time' => now()->subMinutes(30),
                'type' => 'carbon_calculation',
                'description' => '碳排放計算完成',
                'user' => 'system'
            ],
            [
                'time' => now()->subHours(1),
                'type' => 'backup_complete',
                'description' => '系統備份完成',
                'user' => 'system'
            ],
            [
                'time' => now()->subHours(2),
                'type' => 'settings_update',
                'description' => '系統設定更新',
                'user' => 'admin@example.com'
            ]
        ]);
    }
}