<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carbon_emission_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('analysis_date')->comment('分析日期');
            $table->decimal('total_distance', 10, 2)->comment('總距離(公里)');
            $table->integer('total_duration')->comment('總時間(秒)');
            $table->enum('transport_mode', [
                'walking', 'bicycle', 'motorcycle', 'car', 'bus', 'mixed'
            ])->comment('交通工具');
            $table->decimal('carbon_emission', 10, 3)->comment('碳排放量(kg CO2)');
            $table->json('route_details')->nullable()->comment('路線詳細資訊');
            $table->json('ai_analysis')->nullable()->comment('AI分析結果');
            $table->text('suggestions')->nullable()->comment('減碳建議');
            $table->decimal('average_speed', 8, 2)->nullable()->comment('平均速度(km/h)');
            $table->timestamps();
            
            $table->index(['user_id', 'analysis_date']);
            $table->unique(['user_id', 'analysis_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carbon_emission_analyses');
    }
};