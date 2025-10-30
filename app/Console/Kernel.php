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
        // Schedule emissions monitoring to run hourly for automatic quota adjustments
        $schedule->command('emissions:monitor --update-all')->hourly();
        
        // Optional: Daily report generation at midnight
        $schedule->command('emissions:monitor --generate-report')->dailyAt('00:00');
        
        // Optional: Cleanup old data weekly
        $schedule->command('emissions:monitor --cleanup-days=90')->weekly();
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
