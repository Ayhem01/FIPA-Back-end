<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class StopScheduler extends Command
{
    protected $signature = 'scheduler:stop';
    protected $description = 'Arrête le processus du planificateur automatique';

    public function handle()
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Méthode ultra-simple qui fonctionne sur tous les systèmes Windows
            exec('taskkill /F /FI "WINDOWTITLE eq Laravel-Scheduler*" 2>NUL');
            $this->info("Planificateur arrêté.");
        } else {
            exec("killall -9 php 2>/dev/null || echo 'Aucun processus trouvé'");
            $this->info("Planificateur arrêté.");
        }
        
        return Command::SUCCESS;
    }
}