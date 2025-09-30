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
            // 檢查並添加所有缺失的欄位
            
            if (!Schema::hasColumn('carbon_analyses', 'transport_mode')) {
                $table->string('transport_mode', 50)->nullable()->after('analysis_result');
            }
            
            if (!Schema::hasColumn('carbon_analyses', 'total_carbon_emission')) {
                $table->decimal('total_carbon_emission', 10, 3)->default(0)->after('transport_mode');
            }
            
            if (!Schema::hasColumn('carbon_analyses', 'total_distance')) {
                $table->decimal('total_distance', 10, 2)->default(0)->after('total_carbon_emission');
            }
            
            if (!Schema::hasColumn('carbon_analyses', 'total_duration')) {
                $table->integer('total_duration')->default(0)->comment('總時間(秒)')->after('total_distance');
            }
            
            if (!Schema::hasColumn('carbon_analyses', 'average_speed')) {
                $table->decimal('average_speed', 8, 2)->default(0)->after('total_duration');
            }
            
            if (!Schema::hasColumn('carbon_analyses', 'confidence')) {
                $table->decimal('confidence', 5, 2)->default(0)->after('average_speed');
            }
            
            if (!Schema::hasColumn('carbon_analyses', 'route_analysis')) {
                $table->text('route_analysis')->nullable()->after('confidence');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carbon_analyses', function (Blueprint $table) {
            $columns = [
                'transport_mode',
                'total_carbon_emission',
                'total_distance',
                'total_duration',
                'average_speed',
                'confidence',
                'route_analysis'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('carbon_analyses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};