<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gps_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('speed', 8, 2)->default(0);
            $table->decimal('altitude', 8, 2)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->date('date');
            $table->time('time');
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index('date');
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('gps_data');
    }
};
