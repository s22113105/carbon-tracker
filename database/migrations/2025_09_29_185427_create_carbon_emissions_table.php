<?php

// ====================================
// 1. 創建 carbon_emissions 資料表
// database/migrations/2024_01_01_create_carbon_emissions_table.php
// ====================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarbonEmissionsTable extends Migration
{
    public function up()
    {
        Schema::create('carbon_emissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('trip_id')->nullable()->constrained()->onDelete('cascade');
            $table->date('date');
            $table->enum('transport_mode', [
                'walking', 'bicycle', 'motorcycle', 
                'car', 'bus', 'mrt', 'train', 'other'
            ])->default('motorcycle');
            $table->decimal('distance', 10, 2)->comment('距離(公里)');
            $table->decimal('carbon_amount', 10, 3)->comment('碳排放量(kg CO2)');
            $table->timestamps();
            
            // 索引
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'transport_mode']);
            $table->index('trip_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('carbon_emissions');
    }
}