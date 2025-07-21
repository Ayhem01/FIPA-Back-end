<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Helpers\AuthorizationHelper;
use Illuminate\Support\Facades\Log;

class TaskPolicy
{
    /**
     * Determine si l'utilisateur peut voir une tâche spécifique
     */
    public function view(User $user, Task $task): bool
    {
        // Log pour diagnostiquer les problèmes potentiels
        Log::debug('TaskPolicy::view', [
            'user_id' => $user->id,
            'task_user_id' => $task->user_id,
            'task_assignee_id' => $task->assignee_id,
        ]);
        
        // Vérification hiérarchique :
        
        // 1. L'utilisateur est-il admin?
        if (AuthorizationHelper::hasRole('admin')) {
            Log::debug('TaskPolicy::view - Accès autorisé (admin)');
            return true;
        }
        
        // 2. L'utilisateur a-t-il la permission de gérer toutes les tâches?
        if (AuthorizationHelper::can('manage all tasks')) {
            Log::debug('TaskPolicy::view - Accès autorisé (manage all tasks)');
            return true;
        }
        
        // 3. L'utilisateur est-il le créateur?
        if ((int)$task->user_id === (int)$user->id) {
            Log::debug('TaskPolicy::view - Accès autorisé (créateur)');
            return true;
        }
        
        // 4. L'utilisateur est-il l'assigné?
        if ((int)$task->assignee_id === (int)$user->id) {
            Log::debug('TaskPolicy::view - Accès autorisé (assigné)');
            return true;
        }
        
        // Accès refusé par défaut
        Log::debug('TaskPolicy::view - Accès refusé');
        return false;
    }

    /**
     * Determine si l'utilisateur peut voir la liste des tâches
     */
    public function viewAny(User $user): bool
    {
        // Tout utilisateur authentifié peut voir la liste des tâches
        // (le filtrage se fait dans le contrôleur)
        return true;
    }

    /**
     * Determine si l'utilisateur peut créer des tâches
     */
    public function create(User $user): bool
    {
        return AuthorizationHelper::can('create tasks');
    }

    /**
     * Determine si l'utilisateur peut mettre à jour une tâche
     */
    public function update(User $user, Task $task): bool
    {
        // Seul le créateur peut modifier une tâche
        return (int)$task->user_id === (int)$user->id;
    }

    /**
     * Determine si l'utilisateur peut supprimer une tâche
     */
    public function delete(User $user, Task $task): bool
    {
        return AuthorizationHelper::can('delete tasks') &&
               ((int)$task->user_id === (int)$user->id || 
               AuthorizationHelper::hasRole('admin') || 
               AuthorizationHelper::can('manage all tasks'));
    }

    /**
     * Determine si l'utilisateur peut mettre à jour le statut d'une tâche
     */
    public function updateStatus(User $user, Task $task): bool
    {
        // Créateur ou assigné peut modifier le statut
        return (int)$task->user_id === (int)$user->id || 
               (int)$task->assignee_id === (int)$user->id || 
               AuthorizationHelper::hasRole('admin') || 
               AuthorizationHelper::can('manage all tasks');
    }

    /**
     * Determine si l'utilisateur peut déplacer une tâche
     */
    public function move(User $user, Task $task): bool
    {
        // Même logique que updateStatus
        return $this->updateStatus($user, $task);
    }
}