<?php

// ====================================
// 1. 添加缺少的 duration 欄位遷移
// database/migrations/2024_01_04_add_missing_columns_to_trips_table.php
// ====================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsToTripsTable extends Migration
{
    public function up()
    {
        Schema::table('trips', function (Blueprint $table) {
            // 檢查並添加缺少的欄位
            if (!Schema::hasColumn('trips', 'duration')) {
                $table->integer('duration')->default(0)->comment('持續時間(秒)')->after('distance');
            }
            
            if (!Schema::hasColumn('trips', 'avg_speed')) {
                $table->decimal('avg_speed', 8, 2)->nullable()->comment('平均速度(km/h)')->after('trip_type');
            }
            
            if (!Schema::hasColumn('trips', 'max_speed')) {
                $table->decimal('max_speed', 8, 2)->nullable()->comment('最高速度(km/h)')->after('avg_speed');
            }
            
            if (!Schema::hasColumn('trips', 'stop_count')) {
                $table->integer('stop_count')->default(0)->comment('停留次數')->after('max_speed');
            }
        });
    }

    public function down()
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['duration', 'avg_speed', 'max_speed', 'stop_count']);
        });
    }
}