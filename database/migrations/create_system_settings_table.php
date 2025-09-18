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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // 設定鍵
            $table->text('value'); // 設定值
            $table->string('description')->nullable(); // 描述
            $table->enum('type', ['string', 'integer', 'boolean', 'json', 'text'])->default('string'); // 資料類型
            $table->string('group')->default('general'); // 設定群組
            $table->timestamps();

            // 索引
            $table->index('key');
            $table->index('group');
            $table->index(['group', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};