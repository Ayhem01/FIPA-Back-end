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
        $schedule->command('tasks:send-reminders')
        ->everyMinute()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/scheduler.log'));
    }

    protected function scheduleEscalate(Schedule $schedule): void
    {
        // Exécuter la commande tous les jours à minuit
        $schedule->command('blockages:auto-escalate')
                 ->dailyAt('00:00')
                 ->appendOutputTo(storage_path('logs/auto-escalate.log'));
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