<?php
// app/Helpers/AuthorizationHelper.php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AuthorizationHelper
{
    /**
     * Vérifier en toute sécurité si l'utilisateur a un rôle
     */
    public static function hasRole($role): bool
    {
        if (!Auth::check()) return false;
        
        try {
            return Auth::user()->hasRole($role);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la vérification du rôle', [
                'role' => $role,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Vérifier en toute sécurité si l'utilisateur a une permission
     */
    public static function can($permission): bool
    {
        if (!Auth::check()) return false;
        
        try {
            return Auth::user()->can($permission);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la vérification de la permission', [
                'permission' => $permission,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}