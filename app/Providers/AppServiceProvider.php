<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Définir le mapping des noms courts vers les noms de classes complets
        Relation::morphMap([
            'invite' => \App\Models\Invite::class,
            'prospect' => \App\Models\Prospect::class,
            'investisseur' => \App\Models\Investisseur::class, // ou Investor
            'projet' => \App\Models\Project::class, // ou Project
            
            // Pour les stages de pipeline
            'invite_stage' => \App\Models\InvitePipelineStage::class,
            'prospect_stage' => \App\Models\ProspectPipelineStage::class,
            'investisseur_stage' => \App\Models\InvestorPipelineStage::class,
            'projet_stage' => \App\Models\ProjectPipelineStage::class,
        ]);
    }
}
