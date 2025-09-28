<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GpsDataSyncService;
use App\Models\User;
use Carbon\Carbon;

class SyncEsp32GpsData extends Command
{
    protected $signature = 'gps:sync-esp32-data
                            {--user= : 特定用戶ID}
                            {--date= : 特定日期 (Y-m-d)}
                            {--auto-analyze : 自動分析並生成行程}
                            {--batch-process : 處理批次上傳的離線資料}
                            {--cleanup-days= : 清理指定天數前的舊資料}';

    protected $description = '同步ESP32設備的GPS資料到系統中，並可選擇性地自動分析生成行程';

    protected $gpsDataSyncService;

    public function __construct(GpsDataSyncService $gpsDataSyncService)
    {
        parent::__construct();
        $this->gpsDataSyncService = $gpsDataSyncService;
    }

    public function handle()
    {
        $this->info('🚀 開始同步ESP32 GPS資料...');
        
        $userId = $this->option('user');
        $date = $this->option('date');
        $autoAnalyze = $this->option('auto-analyze');
        $batchProcess = $this->option('batch-process');
        $cleanupDays = $this->option('cleanup-days');
        
        try {
            // 清理舊資料
            if ($cleanupDays) {
                $this->handleCleanup($cleanupDays);
            }
            
            // 批次處理離線資料
            if ($batchProcess) {
                $this->handleBatchProcess($userId);
                return 0;
            }
            
            // 一般同步流程
            $this->handleSync($userId, $date, $autoAnalyze);
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ 同步失敗: ' . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * 處理一般同步
     */
    private function handleSync($userId, $date, $autoAnalyze)
    {
        $this->info('📊 同步GPS資料...');
        
        if ($userId) {
            $users = [User::findOrFail($userId)];
            $this->info("處理用戶: {$users[0]->name} (ID: {$userId})");
        } else {
            // 獲取所有有GPS資料的用戶
            $users = User::whereHas('gpsRecords')
                        ->orWhereExists(function ($query) {
                            $query->select(\DB::raw(1))
                                  ->from('gps_tracks')
                                  ->whereRaw('gps_tracks.user_id = users.id');
                        })
                        ->get();
            $this->info("找到 " . count($users) . " 個有GPS資料的用戶");
        }
        
        $totalSynced = 0;
        $totalTrips = 0;
        
        foreach ($users as $user) {
            $this->info("👤 處理用戶: {$user->name}");
            
            if ($autoAnalyze) {
                // 同步並分析
                $result = $this->gpsDataSyncService->analyzeAndCreateTrips(
                    $user->id, 
                    $date ?: Carbon::today()->format('Y-m-d')
                );
                
                $totalSynced += $result['synced_gps_count'];
                $totalTrips += $result['trips_created'];
                
                $this->line("  ✅ 同步了 {$result['synced_gps_count']} 筆GPS資料");
                $this->line("  🗺️  生成了 {$result['trips_created']} 筆行程記錄");
                
            } else {
                // 只同步，不分析
                $synced = $this->gpsDataSyncService->syncGpsTracksToRecords($user->id, $date);
                $totalSynced += $synced;
                
                $this->line("  ✅ 同步了 {$synced} 筆GPS資料");
            }
        }
        
        $this->newLine();
        $this->info("🎉 同步完成!");
        $this->table([
            '項目', '數量'
        ], [
            ['處理用戶數', count($users)],
            ['同步GPS點數', $totalSynced],
            ['生成行程數', $totalTrips]
        ]);
        
        if (!$autoAnalyze && $totalSynced > 0) {
            $this->warn('💡 提示: 使用 --auto-analyze 參數可以自動分析並生成行程記錄');
        }
    }
    
    /**
     * 處理批次上傳的離線資料
     */
    private function handleBatchProcess($userId)
    {
        $this->info('📦 處理批次上傳的離線資料...');
        
        if ($userId) {
            $users = [User::findOrFail($userId)];
        } else {
            // 查找有未處理GPS資料的用戶
            $users = User::whereExists(function ($query) {
                $query->select(\DB::raw(1))
                      ->from('gps_tracks')
                      ->whereRaw('gps_tracks.user_id = users.id')
                      ->where('is_processed', false);
            })->get();
        }
        
        $this->info("找到 " . count($users) . " 個用戶有未處理的GPS資料");
        
        $totalDays = 0;
        $totalTrips = 0;
        
        foreach ($users as $user) {
            $this->info("👤 處理用戶: {$user->name}");
            
            $result = $this->gpsDataSyncService->processBatchUpload($user->id);
            
            $totalDays += $result['processed_days'];
            $totalTrips += $result['total_trips'];
            
            $this->line("  📅 處理了 {$result['processed_days']} 天的資料");
            $this->line("  🗺️  生成了 {$result['total_trips']} 筆行程記錄");
        }
        
        $this->newLine();
        $this->info("🎉 批次處理完成!");
        $this->table([
            '項目', '數量'
        ], [
            ['處理用戶數', count($users)],
            ['處理天數', $totalDays],
            ['生成行程數', $totalTrips]
        ]);
    }
    
    /**
     * 處理資料清理
     */
    private function handleCleanup($days)
    {
        $this->info("🧹 清理 {$days} 天前的舊GPS資料...");
        
        if (!$this->confirm("確定要刪除 {$days} 天前的GPS資料嗎？")) {
            $this->info('取消清理操作');
            return;
        }
        
        $result = $this->gpsDataSyncService->cleanupOldGpsData($days);
        
        $this->info("🎉 清理完成!");
        $this->table([
            '項目', '數量'
        ], [
            ['刪除gps_tracks記錄', $result['deleted_tracks']],
            ['刪除gps_records記錄', $result['deleted_records']]
        ]);
    }
}