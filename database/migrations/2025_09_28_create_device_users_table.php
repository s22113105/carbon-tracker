<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('device_users')) {
            Schema::create('device_users', function (Blueprint $table) {
                $table->id();
                $table->string('device_id')->unique();
                $table->unsignedBigInteger('user_id');
                $table->string('device_name')->nullable();
                $table->string('device_type')->default('ESP32');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                
                $table->index(['device_id', 'user_id']);
            });
            
            // 插入預設資料
            DB::table('device_users')->insert([
                [
                    'device_id' => 'ESP32_CARBON_001',
                    'user_id' => 1,
                    'device_name' => 'ESP32 裝置 #1',
                    'device_type' => 'ESP32',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'device_id' => 'ESP32_CARBON_002',
                    'user_id' => 2,
                    'device_name' => 'ESP32 裝置 #2',
                    'device_type' => 'ESP32',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('device_users');
    }
};