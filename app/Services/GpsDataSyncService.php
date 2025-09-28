<?php

namespace App\Services;

use App\Models\GpsRecord;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GpsDataSyncService
{
    /**
     * 將gps_tracks表的資料同步到gps_records表
     * 這樣可以讓ESP32的實體資料與系統統一處理
     */
    public function syncGpsTracksToRecords($userId = null, $dateFilter = null)
    {
        Log::info('開始同步GPS資料', ['user_id' => $userId, 'date' => $dateFilter]);
        
        try {
            // 建立查詢條件
            $query = DB::table('gps_tracks')
                ->where('is_processed', false);
                
            if ($userId) {
                $query->where('user_id', $userId);
            }
            
            if ($dateFilter) {
                $query->whereDate('recorded_at', $dateFilter);
            }
            
            // 分批處理避免記憶體問題
            $batchSize = 1000;
            $processed = 0;
            
            $query->orderBy('recorded_at', 'asc')
                  ->chunk($batchSize, function ($gpsTracksChunk) use (&$processed) {
                      $this->processBatch($gpsTracksChunk);
                      $processed += count($gpsTracksChunk);
                      Log::info("已處理 {$processed} 筆GPS資料");
                  });
            
            Log::info('GPS資料同步完成', ['total_processed' => $processed]);
            return $processed;
            
        } catch (\Exception $e) {
            Log::error('GPS資料同步失敗', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * 處理單批次的GPS資料
     */
    private function processBatch($gpsTracksChunk)
    {
        $recordsToInsert = [];
        $trackIdsToUpdate = [];
        
        foreach ($gpsTracksChunk as $track) {
            // 檢查是否已經存在相同的記錄
            $existingRecord = GpsRecord::where('user_id', $track->user_id)
                ->where('recorded_at', $track->recorded_at)
                ->where('latitude', $track->latitude)
                ->where('longitude', $track->longitude)
                ->first();
                
            if (!$existingRecord) {
                // 準備插入gps_records表的資料
                $recordsToInsert[] = [
                    'user_id' => $track->user_id,
                    'latitude' => $track->latitude,
                    'longitude' => $track->longitude,
                    'recorded_at' => $track->recorded_at,
                    'accuracy' => $track->accuracy ?? 10,
                    'speed' => $track->speed ?? 0,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            
            $trackIdsToUpdate[] = $track->id;
        }
        
        // 批量插入gps_records
        if (!empty($recordsToInsert)) {
            DB::table('gps_records')->insert($recordsToInsert);
            Log::info('批量插入GPS記錄', ['count' => count($recordsToInsert)]);
        }
        
        // 標記gps_tracks為已處理
        if (!empty($trackIdsToUpdate)) {
            DB::table('gps_tracks')
                ->whereIn('id', $trackIdsToUpdate)
                ->update(['is_processed' => true, 'updated_at' => now()]);
        }
    }
    
    /**
     * 自動分析當日GPS資料並生成行程
     */
    public function analyzeAndCreateTrips($userId, $date = null)
    {
        $date = $date ?: Carbon::today()->format('Y-m-d');
        
        Log::info('開始分析GPS資料生成行程', ['user_id' => $userId, 'date' => $date]);
        
        try {
            // 先同步ESP32資料
            $syncedCount = $this->syncGpsTracksToRecords($userId, $date);
            
            // 使用TripAnalysisService分析行程
            $tripAnalysisService = app(TripAnalysisService::class);
            $trips = $tripAnalysisService->analyzeTripsForDate($userId, $date);
            
            Log::info('行程分析完成', [
                'user_id' => $userId,
                'date' => $date,
                'synced_gps' => $syncedCount,
                'trips_created' => count($trips)
            ]);
            
            return [
                'synced_gps_count' => $syncedCount,
                'trips_created' => count($trips),
                'trips' => $trips
            ];
            
        } catch (\Exception $e) {
            Log::error('GPS分析失敗', [
                'user_id' => $userId,
                'date' => $date,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * 檢查並處理離線上傳的批次資料
     */
    public function processBatchUpload($userId)
    {
        Log::info('處理批次上傳資料', ['user_id' => $userId]);
        
        try {
            // 查找該用戶最近未處理的GPS資料
            $unprocessedTracks = DB::table('gps_tracks')
                ->where('user_id', $userId)
                ->where('is_processed', false)
                ->whereDate('recorded_at', '>=', Carbon::now()->subDays(7)) // 只處理一週內的資料
                ->orderBy('recorded_at', 'asc')
                ->get();
                
            if ($unprocessedTracks->isEmpty()) {
                Log::info('沒有未處理的GPS資料');
                return ['processed_days' => 0, 'total_trips' => 0];
            }
            
            // 按日期分組處理
            $dayGroups = $unprocessedTracks->groupBy(function ($track) {
                return Carbon::parse($track->recorded_at)->format('Y-m-d');
            });
            
            $totalTrips = 0;
            $processedDays = 0;
            
            foreach ($dayGroups as $date => $tracks) {
                Log::info("處理日期 {$date} 的 " . count($tracks) . " 筆GPS資料");
                
                $result = $this->analyzeAndCreateTrips($userId, $date);
                $totalTrips += $result['trips_created'];
                $processedDays++;
            }
            
            Log::info('批次處理完成', [
                'user_id' => $userId,
                'processed_days' => $processedDays,
                'total_trips' => $totalTrips
            ]);
            
            return [
                'processed_days' => $processedDays,
                'total_trips' => $totalTrips
            ];
            
        } catch (\Exception $e) {
            Log::error('批次處理失敗', ['user_id' => $userId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * 獲取用戶的即時GPS狀態
     */
    public function getRealTimeGpsStatus($userId)
    {
        try {
            // 最近的GPS記錄
            $latestGps = DB::table('gps_tracks')
                ->where('user_id', $userId)
                ->orderBy('recorded_at', 'desc')
                ->first();
                
            // 今日統計
            $todayStats = DB::table('gps_tracks')
                ->where('user_id', $userId)
                ->whereDate('recorded_at', Carbon::today())
                ->selectRaw('
                    COUNT(*) as total_points,
                    MIN(recorded_at) as first_record,
                    MAX(recorded_at) as last_record,
                    COUNT(CASE WHEN is_processed = 0 THEN 1 END) as unprocessed_count
                ')
                ->first();
                
            // 今日行程統計
            $todayTrips = Trip::where('user_id', $userId)
                ->whereDate('start_time', Carbon::today())
                ->count();
                
            return [
                'latest_gps' => $latestGps ? [
                    'latitude' => $latestGps->latitude,
                    'longitude' => $latestGps->longitude,
                    'speed' => $latestGps->speed,
                    'recorded_at' => $latestGps->recorded_at,
                    'minutes_ago' => Carbon::parse($latestGps->recorded_at)->diffInMinutes(now())
                ] : null,
                'today_stats' => [
                    'total_gps_points' => $todayStats->total_points ?? 0,
                    'unprocessed_points' => $todayStats->unprocessed_count ?? 0,
                    'first_record_at' => $todayStats->first_record,
                    'last_record_at' => $todayStats->last_record,
                    'trips_generated' => $todayTrips
                ],
                'device_status' => $this->getDeviceStatus($userId)
            ];
            
        } catch (\Exception $e) {
            Log::error('獲取即時GPS狀態失敗', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * 獲取設備狀態
     */
    private function getDeviceStatus($userId)
    {
        // 檢查是否有關聯的設備
        $deviceUser = DB::table('device_users')
            ->where('user_id', $userId)
            ->first();
            
        if (!$deviceUser) {
            return ['status' => 'no_device', 'message' => '未註冊設備'];
        }
        
        // 檢查最近是否有資料
        $recentData = DB::table('gps_tracks')
            ->where('user_id', $userId)
            ->where('recorded_at', '>=', Carbon::now()->subMinutes(10))
            ->exists();
            
        return [
            'status' => $recentData ? 'online' : 'offline',
            'device_id' => $deviceUser->device_id,
            'last_seen' => $recentData ? '線上' : '離線'
        ];
    }
    
    /**
     * 清理舊的GPS資料（保留指定天數）
     */
    public function cleanupOldGpsData($daysToKeep = 90)
    {
        $cutoffDate = Carbon::now()->subDays($daysToKeep);
        
        Log::info('開始清理舊GPS資料', ['cutoff_date' => $cutoffDate->format('Y-m-d')]);
        
        try {
            // 清理gps_tracks表
            $deletedTracks = DB::table('gps_tracks')
                ->where('recorded_at', '<', $cutoffDate)
                ->where('is_processed', true)
                ->delete();
                
            // 清理gps_records表（保留行程相關的資料）
            $deletedRecords = DB::table('gps_records')
                ->where('recorded_at', '<', $cutoffDate)
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                          ->from('trips')
                          ->whereRaw('DATE(trips.start_time) = DATE(gps_records.recorded_at)')
                          ->whereRaw('trips.user_id = gps_records.user_id');
                })
                ->delete();
                
            Log::info('GPS資料清理完成', [
                'deleted_tracks' => $deletedTracks,
                'deleted_records' => $deletedRecords
            ]);
            
            return [
                'deleted_tracks' => $deletedTracks,
                'deleted_records' => $deletedRecords
            ];
            
        } catch (\Exception $e) {
            Log::error('GPS資料清理失敗', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}