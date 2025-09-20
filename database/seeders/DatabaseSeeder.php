<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 創建使用者和GPS測試資料
        $this->call([
            GpsDataSeeder::class,
        ]);
    }
}