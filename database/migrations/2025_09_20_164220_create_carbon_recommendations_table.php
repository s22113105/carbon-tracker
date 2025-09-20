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
        Schema::create('carbon_recommendations', function (Blueprint $table) {
            $table->id();
            
            // 關聯到碳分析記錄
            $table->foreignId('carbon_analysis_id')
                  ->constrained('carbon_analyses')
                  ->onDelete('cascade')
                  ->comment('碳分析記錄ID');
            
            // 建議內容
            $table->string('title')
                  ->comment('建議標題');
            $table->text('description')
                  ->comment('建議描述');
            
            // 減碳潛力
            $table->decimal('potential_reduction', 8, 3)
                  ->comment('預期減碳量 (kg CO2)');
            
            // 實施難度
            $table->enum('difficulty', ['easy', 'medium', 'hard'])
                  ->comment('實施難度');
            
            // 建議分類
            $table->string('category', 50)
                  ->nullable()
                  ->comment('建議分類');
            
            // 用戶反馈
            $table->boolean('is_implemented')
                  ->default(false)
                  ->comment('是否已實施');
            $table->timestamp('implemented_at')
                  ->nullable()
                  ->comment('實施時間');
            $table->tinyInteger('user_rating')
                  ->nullable()
                  ->comment('用戶評分 (1-5)');
            
            $table->timestamps();
            
            // 建立索引
            $table->index(['carbon_analysis_id', 'difficulty'], 'idx_analysis_difficulty');
            $table->index('category', 'idx_category');
            $table->index('is_implemented', 'idx_implemented');
        });
    }

    /**
     * 回滾遷移
     */
    public function down(): void
    {
        Schema::dropIfExists('carbon_recommendations');
    }
};
