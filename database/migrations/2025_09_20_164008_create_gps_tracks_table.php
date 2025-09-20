<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gps_tracks', function (Blueprint $table) {
            $table->id();
            
            // 關聯用戶
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('用戶ID');
            
            // GPS 座標資訊
            $table->decimal('latitude', 10, 8)
                  ->comment('緯度 (精確到8位小數)');
            $table->decimal('longitude', 11, 8)
                  ->comment('經度 (精確到8位小數)');
            
            // 時間戳記
            $table->timestamp('recorded_at')
                  ->comment('GPS記錄時間');
            
            // 額外的GPS資訊
            $table->decimal('altitude', 8, 2)
                  ->nullable()
                  ->comment('海拔高度 (公尺)');
            $table->decimal('speed', 8, 2)
                  ->nullable()
                  ->comment('速度 (km/h)');
            $table->decimal('accuracy', 8, 2)
                  ->nullable()
                  ->comment('精確度 (公尺)');
            $table->decimal('bearing', 8, 2)
                  ->nullable()
                  ->comment('方位角 (度)');
            
            // 處理狀態
            $table->boolean('is_processed')
                  ->default(false)
                  ->comment('是否已處理');
            $table->string('device_type', 50)
                  ->nullable()
                  ->comment('設備類型');
            
            // 系統時間戳記
            $table->timestamps();
            
            // 建立索引以提升查詢效能
            $table->index(['user_id', 'recorded_at'], 'idx_user_time');
            $table->index(['user_id', 'is_processed'], 'idx_user_processed');
            $table->index('recorded_at', 'idx_recorded_at');
            
            // 複合索引用於範圍查詢
            $table->index(['user_id', 'recorded_at', 'is_processed'], 'idx_user_time_processed');
        });
    }

    /**
     * 回滾遷移
     */
    public function down(): void
    {
        Schema::dropIfExists('gps_tracks');
    }
};
