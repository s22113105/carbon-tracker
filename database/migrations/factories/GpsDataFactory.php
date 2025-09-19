<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class GpsDataFactory extends Factory
{
    public function definition()
    {
        $baseDate = Carbon::now()->subDays(rand(1, 30));
        
        return [
            'user_id' => 1,
            'latitude' => $this->faker->latitude(22.5, 22.8), // 高雄地區
            'longitude' => $this->faker->longitude(120.2, 120.4),
            'speed' => $this->faker->randomFloat(2, 0, 80),
            'altitude' => $this->faker->randomFloat(2, 0, 100),
            'accuracy' => $this->faker->randomFloat(2, 1, 10),
            'date' => $baseDate->format('Y-m-d'),
            'time' => $baseDate->format('H:i:s'),
            'created_at' => $baseDate,
            'updated_at' => $baseDate,
        ];
    }
}