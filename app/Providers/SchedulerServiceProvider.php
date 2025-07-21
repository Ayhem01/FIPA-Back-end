<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

class SchedulerServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        // Ne pas exécuter en console ou en test
        if ($this->app->runningInConsole() || $this->app->environment('testing')) {
            return;
        }

        // Vérifier si un planificateur est déjà en cours d'exécution
        $lockFile = storage_path('framework/scheduler.lock');
        
        if (file_exists($lockFile)) {
            // Si le fichier existe et date de moins de 5 minutes, ne rien faire
            if (time() - filemtime($lockFile) < 300) {
                return;
            }
            // Sinon, supprimer le verrou obsolète
            @unlink($lockFile);
        }
        
        // Créer le fichier de verrouillage
        file_put_contents($lockFile, time());
        
        // Démarrer le planificateur en arrière-plan
        register_shutdown_function(function () use ($lockFile) {
            // Éviter les conflits de fichiers
            if (PHP_OS_FAMILY === 'Windows') {
                $windowTitle = "Laravel_Scheduler_" . time();
                pclose(popen("start \"{$windowTitle}\" /B /MIN php \"" . 
                    base_path('artisan') . "\" schedule:work > NUL", 'r'));
                
                file_put_contents($lockFile, $windowTitle);
            } else {
                exec('php ' . base_path('artisan') . ' schedule:work > /dev/null 2>&1 &');
            }
        });
    }
}