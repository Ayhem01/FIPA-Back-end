<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Blockage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

class EscalateOldBlockages extends Command
{
    protected $signature = 'blockages:auto-escalate {--days=3 : Nombre de jours avant escalade} {--admin-id= : ID de l\'admin pour l\'escalade}';
    protected $description = 'Escalade automatiquement les blocages non résolus depuis plusieurs jours';

    public function handle()
    {
        $days = $this->option('days');
        
        // Récupérer l'ID admin depuis les options de commande, la configuration ou par défaut
        $adminId = $this->option('admin-id') ?? 
                  Config::get('blockages.admin_id') ?? 
                  env('DEFAULT_ADMIN_ID', 1);
        
        $admin = User::find($adminId);
        
        if (!$admin) {
            $this->error("Aucun administrateur trouvé avec l'ID {$adminId}");
            return 1;
        }

        $this->info("Administrateur sélectionné: {$admin->name} (ID: {$admin->id})");

        // Trouver les blocages actifs de plus de X jours non encore escaladés
        $deadline = Carbon::now()->subDays($days);
        $oldBlockages = Blockage::where('status', 'actif')
            ->where('is_escalated', false)
            ->where('created_at', '<', $deadline)
            ->get();

        $count = $oldBlockages->count();
        if ($count === 0) {
            $this->info('Aucun blocage à escalader automatiquement');
            return 0;
        }

        $this->info("Escalade automatique de {$count} blocage(s) à l'administrateur #{$admin->id}");

        // Escalader chaque blocage
        foreach ($oldBlockages as $blockage) {
            try {
                $blockage->autoEscalate($admin->id);
                $this->line("Blocage #{$blockage->id} escaladé: {$blockage->name}");
            } catch (\Exception $e) {
                $this->error("Erreur lors de l'escalade du blocage #{$blockage->id}: {$e->getMessage()}");
            }
        }

        $this->info('Escalade automatique terminée avec succès');
        return 0;
    }
}