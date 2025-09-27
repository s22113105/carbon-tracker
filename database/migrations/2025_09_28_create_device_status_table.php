<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('device_status')) {
            Schema::create('device_status', function (Blueprint $table) {
                $table->id();
                $table->string('device_id')->unique();
                $table->integer('battery_level')->default(100);
                $table->timestamp('last_seen')->nullable();
                $table->string('firmware_version')->nullable();
                $table->string('ip_address')->nullable();
                $table->boolean('is_online')->default(false);
                $table->json('meta_data')->nullable();
                $table->timestamps();
                
                $table->index('device_id');
                $table->index('last_seen');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('device_status');
    }
};