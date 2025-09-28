<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        // 每小時同步ESP32資料並分析
        $schedule->command('gps:sync-esp32-data --auto-analyze')
                ->hourly()
                ->between('6:00', '23:00');
                
        // 每天凌晨處理批次離線資料
        $schedule->command('gps:sync-esp32-data --batch-process')
                ->dailyAt('02:00');
                
        // 每週清理90天前的舊資料
        $schedule->command('gps:sync-esp32-data --cleanup-days=90')
                ->weekly()
                ->sundays()
                ->at('03:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
    
}

