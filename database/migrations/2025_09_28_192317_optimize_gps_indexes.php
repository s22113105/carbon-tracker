<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 優化 gps_records 表的索引
        if (Schema::hasTable('gps_records')) {
            // 檢查並添加座標索引
            if (!$this->indexExists('gps_records', 'idx_gps_coordinates')) {
                DB::statement('CREATE INDEX idx_gps_coordinates ON gps_records (latitude, longitude)');
            }
            
            // 檢查並添加用戶時間座標複合索引
            if (!$this->indexExists('gps_records', 'idx_gps_user_time_coords')) {
                DB::statement('CREATE INDEX idx_gps_user_time_coords ON gps_records (user_id, recorded_at, latitude, longitude)');
            }
            
            // 如果有speed欄位，添加速度索引
            if (Schema::hasColumn('gps_records', 'speed') && !$this->indexExists('gps_records', 'idx_gps_user_speed')) {
                DB::statement('CREATE INDEX idx_gps_user_speed ON gps_records (user_id, speed)');
            }
        }

        // 優化 trips 表的索引
        if (Schema::hasTable('trips')) {
            // 交通工具統計索引
            if (!$this->indexExists('trips', 'idx_trips_user_mode_time')) {
                DB::statement('CREATE INDEX idx_trips_user_mode_time ON trips (user_id, transport_mode, start_time)');
            }
            
            // 行程類型索引
            if (!$this->indexExists('trips', 'idx_trips_user_type_time')) {
                DB::statement('CREATE INDEX idx_trips_user_type_time ON trips (user_id, trip_type, start_time)');
            }
            
            // 距離統計索引
            if (!$this->indexExists('trips', 'idx_trips_user_distance')) {
                DB::statement('CREATE INDEX idx_trips_user_distance ON trips (user_id, distance)');
            }
            
            // 起點座標索引
            if (!$this->indexExists('trips', 'idx_trips_start_coords')) {
                DB::statement('CREATE INDEX idx_trips_start_coords ON trips (start_latitude, start_longitude)');
            }
            
            // 終點座標索引
            if (!$this->indexExists('trips', 'idx_trips_end_coords')) {
                DB::statement('CREATE INDEX idx_trips_end_coords ON trips (end_latitude, end_longitude)');
            }
            
            // 完整查詢複合索引
            if (!$this->indexExists('trips', 'idx_trips_complete_query')) {
                DB::statement('CREATE INDEX idx_trips_complete_query ON trips (user_id, start_time, end_time, transport_mode)');
            }
        }

        // 如果存在 gps_tracks 表，也進行優化
        if (Schema::hasTable('gps_tracks')) {
            // 用戶時間索引
            if (!$this->indexExists('gps_tracks', 'idx_tracks_user_time')) {
                DB::statement('CREATE INDEX idx_tracks_user_time ON gps_tracks (user_id, recorded_at)');
            }
            
            // 座標索引
            if (!$this->indexExists('gps_tracks', 'idx_tracks_coordinates')) {
                DB::statement('CREATE INDEX idx_tracks_coordinates ON gps_tracks (latitude, longitude)');
            }
            
            // 處理狀態索引
            if (Schema::hasColumn('gps_tracks', 'is_processed') && !$this->indexExists('gps_tracks', 'idx_tracks_processed')) {
                DB::statement('CREATE INDEX idx_tracks_processed ON gps_tracks (user_id, is_processed)');
            }
        }

        // 創建行程分析結果暫存表（如果不存在）
        if (!Schema::hasTable('trip_analysis_cache')) {
            Schema::create('trip_analysis_cache', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->date('analysis_date');
                $table->json('analysis_result')->nullable();
                $table->integer('trips_count')->default(0);
                $table->decimal('total_distance', 10, 2)->default(0);
                $table->timestamp('analyzed_at');
                $table->timestamps();
                
                // 主要查詢索引
                $table->unique(['user_id', 'analysis_date'], 'idx_cache_user_date');
                $table->index(['analyzed_at'], 'idx_cache_analyzed_time');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 刪除我們建立的索引
        $indexesToDrop = [
            // gps_records 表的索引
            ['table' => 'gps_records', 'index' => 'idx_gps_coordinates'],
            ['table' => 'gps_records', 'index' => 'idx_gps_user_time_coords'],
            ['table' => 'gps_records', 'index' => 'idx_gps_user_speed'],
            
            // trips 表的索引
            ['table' => 'trips', 'index' => 'idx_trips_user_mode_time'],
            ['table' => 'trips', 'index' => 'idx_trips_user_type_time'],
            ['table' => 'trips', 'index' => 'idx_trips_user_distance'],
            ['table' => 'trips', 'index' => 'idx_trips_start_coords'],
            ['table' => 'trips', 'index' => 'idx_trips_end_coords'],
            ['table' => 'trips', 'index' => 'idx_trips_complete_query'],
            
            // gps_tracks 表的索引
            ['table' => 'gps_tracks', 'index' => 'idx_tracks_user_time'],
            ['table' => 'gps_tracks', 'index' => 'idx_tracks_coordinates'],
            ['table' => 'gps_tracks', 'index' => 'idx_tracks_processed'],
        ];

        foreach ($indexesToDrop as $item) {
            if (Schema::hasTable($item['table']) && $this->indexExists($item['table'], $item['index'])) {
                DB::statement("DROP INDEX {$item['index']} ON {$item['table']}");
            }
        }

        // 刪除快取表
        Schema::dropIfExists('trip_analysis_cache');
    }

    /**
     * 檢查索引是否存在
     */
    private function indexExists($table, $index)
    {
        try {
            $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]);
            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
};