<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //  'App\Models\Model' => 'App\Policies\ModelPolicy',
        \App\Models\Task::class => \App\Policies\TaskPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // La nouvelle façon de configurer Passport 12.4
        
        // Définition des scopes
        Passport::tokensCan([
            '2fa-temp' => 'Accès temporaire pour vérification 2FA',
            'full-access' => 'Accès complet après authentification réussie',
        ]);
        
        // Configuration des durées d'expiration des tokens
        Passport::tokensExpireIn(now()->addDays(15)); // 15 jours pour les tokens standard
        Passport::refreshTokensExpireIn(now()->addDays(30)); // 30 jours pour les refresh tokens
        Passport::personalAccessTokensExpireIn(now()->addDays(15)); // 15 jours pour les tokens personnels
    }
}