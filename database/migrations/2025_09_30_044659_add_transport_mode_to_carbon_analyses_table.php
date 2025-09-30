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
        Schema::table('carbon_analyses', function (Blueprint $table) {
            // 檢查欄位是否存在,不存在才添加
            if (!Schema::hasColumn('carbon_analyses', 'transport_mode')) {
                $table->string('transport_mode', 50)->nullable()->after('analysis_result');
            }
            
            if (!Schema::hasColumn('carbon_analyses', 'total_carbon_emission')) {
                $table->decimal('total_carbon_emission', 10, 3)->default(0)->after('transport_mode');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carbon_analyses', function (Blueprint $table) {
            if (Schema::hasColumn('carbon_analyses', 'transport_mode')) {
                $table->dropColumn('transport_mode');
            }
            
            if (Schema::hasColumn('carbon_analyses', 'total_carbon_emission')) {
                $table->dropColumn('total_carbon_emission');
            }
        });
    }
};