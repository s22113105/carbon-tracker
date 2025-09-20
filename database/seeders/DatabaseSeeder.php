<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * 執行資料庫種子
     */
    public function run(): void
    {
        $this->call([
            GpsTrackSeeder::class,
            // 如果你有其他的 seeder 也可以在這裡加入
        ]);
    }
}