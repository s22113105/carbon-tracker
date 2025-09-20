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
        Schema::create('carbon_analyses', function (Blueprint $table) {
            $table->id();
            
            // 關聯用戶
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade')
                  ->comment('用戶ID');
            
            // 分析期間
            $table->date('start_date')
                  ->comment('分析開始日期');
            $table->date('end_date')
                  ->comment('分析結束日期');
            
            // 分析結果 (JSON格式儲存完整的AI回應)
            $table->json('analysis_result')
                  ->comment('完整的分析結果JSON');
            
            // 關鍵指標 (方便查詢和統計)
            $table->decimal('total_carbon_emission', 8, 3)
                  ->comment('總碳排放量 (kg CO2)');
            $table->decimal('total_distance', 8, 2)
                  ->nullable()
                  ->comment('總距離 (km)');
            $table->integer('total_time')
                  ->nullable()
                  ->comment('總時間 (分鐘)');
            
            // 碳足跡等級
            $table->enum('footprint_level', ['low', 'medium', 'high'])
                  ->nullable()
                  ->comment('碳足跡等級');
            
            // 改善潛力
            $table->integer('improvement_potential')
                  ->nullable()
                  ->comment('改善潛力百分比');
            
            // OpenAI 相關資訊
            $table->string('openai_model', 50)
                  ->nullable()
                  ->comment('使用的OpenAI模型');
            $table->integer('tokens_used')
                  ->nullable()
                  ->comment('使用的tokens數量');
            $table->decimal('api_cost', 8, 4)
                  ->nullable()
                  ->comment('API呼叫成本');
            
            // 分析狀態
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                  ->default('pending')
                  ->comment('分析狀態');
            $table->text('error_message')
                  ->nullable()
                  ->comment('錯誤訊息');
            
            // 系統時間戳記
            $table->timestamps();
            
            // 建立索引
            $table->index(['user_id', 'created_at'], 'idx_user_created');
            $table->index(['user_id', 'start_date', 'end_date'], 'idx_user_period');
            $table->index('status', 'idx_status');
            $table->index('footprint_level', 'idx_footprint_level');
            
            // 確保同一用戶同一期間不會重複分析
            $table->unique(['user_id', 'start_date', 'end_date'], 'unique_user_period');
        });
    }

    /**
     * 回滾遷移
     */
    public function down(): void
    {
        Schema::dropIfExists('carbon_analyses');
    }
};
