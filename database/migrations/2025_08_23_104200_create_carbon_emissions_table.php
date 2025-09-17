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
        Schema::create('carbon_emissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('trip_id')->nullable()->constrained()->onDelete('cascade');
            $table->date('emission_date');
            $table->enum('transport_mode', ['walking', 'bus', 'mrt', 'car', 'motorcycle']);
            $table->decimal('distance', 8, 2); // 距離 km
            $table->decimal('co2_emission', 8, 3); // CO2 排放量 kg
            $table->text('ai_suggestion')->nullable(); // AI 建議
            $table->timestamps();
            
            $table->index(['user_id', 'emission_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carbon_emissions');
    }
};
