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
        Schema::create('geofences', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 圍欄名稱
            $table->text('description')->nullable(); // 描述
            $table->decimal('latitude', 10, 8); // 中心點緯度
            $table->decimal('longitude', 11, 8); // 中心點經度
            $table->integer('radius'); // 半徑（公尺）
            $table->enum('type', ['office', 'restricted', 'parking', 'custom'])->default('custom'); // 圍欄類型
            $table->boolean('is_active')->default(true); // 是否啟用
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // 建立者
            $table->timestamps();

            // 索引
            $table->index(['latitude', 'longitude']);
            $table->index(['is_active', 'type']);
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geofences');
    }
};