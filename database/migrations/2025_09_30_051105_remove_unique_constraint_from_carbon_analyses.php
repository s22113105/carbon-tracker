<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 查找並刪除唯一鍵約束
        Schema::table('carbon_analyses', function (Blueprint $table) {
            // 嘗試刪除可能存在的唯一鍵
            try {
                $table->dropUnique('unique_user_period');
            } catch (\Exception $e) {
                // 如果約束不存在,忽略錯誤
            }
            
            try {
                $table->dropUnique(['user_id', 'start_date', 'end_date']);
            } catch (\Exception $e) {
                // 如果約束不存在,忽略錯誤
            }
        });
    }

    public function down(): void
    {
        Schema::table('carbon_analyses', function (Blueprint $table) {
            $table->unique(['user_id', 'start_date', 'end_date'], 'unique_user_period');
        });
    }
};