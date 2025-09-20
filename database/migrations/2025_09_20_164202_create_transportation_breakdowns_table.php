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
        Schema::create('transportation_breakdowns', function (Blueprint $table) {
            $table->id();
            
            // 關聯到碳分析記錄
            $table->foreignId('carbon_analysis_id')
                  ->constrained('carbon_analyses')
                  ->onDelete('cascade')
                  ->comment('碳分析記錄ID');
            
            // 交通工具類型
            $table->enum('transportation_type', ['walking', 'bicycle', 'motorcycle', 'car', 'bus', 'mrt', 'train'])
                  ->comment('交通工具類型');
            
            // 使用統計
            $table->decimal('distance', 8, 2)
                  ->comment('距離 (km)');
            $table->integer('time_minutes')
                  ->comment('時間 (分鐘)');
            $table->decimal('carbon_emission', 8, 3)
                  ->comment('碳排放量 (kg CO2)');
            
            // 使用頻率
            $table->integer('trip_count')
                  ->default(1)
                  ->comment('行程次數');
            $table->decimal('average_speed', 8, 2)
                  ->nullable()
                  ->comment('平均速度 (km/h)');
            
            // 成本資訊 (可選)
            $table->decimal('estimated_cost', 8, 2)
                  ->nullable()
                  ->comment('預估花費');
            
            $table->timestamps();
            
            // 建立索引
            $table->index(['carbon_analysis_id', 'transportation_type'], 'idx_analysis_transport');
            $table->index('transportation_type', 'idx_transport_type');
        });
    }

    /**
     * 回滾遷移
     */
    public function down(): void
    {
        Schema::dropIfExists('transportation_breakdowns');
    }
};
