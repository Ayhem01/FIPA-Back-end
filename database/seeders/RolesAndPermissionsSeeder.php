<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Reset des caches de permission
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'api'; // Utilisé pour tout le projet React + Laravel API

        // Définir les permissions des tâches
        $taskPermissions = [
            'view tasks',
            'create tasks',
            'edit tasks',
            'delete tasks',
            'manage all tasks',
        ];

        // Définir les permissions des utilisateurs
        $userPermissions = [
            'view users',
            'create users',
            'edit users',
            'delete users',
        ];
        $blockagePermissions = [
            'view blockages',
            'create blockages',
            'edit blockages',
            'resolve blockages', 
            'escalate blockages',
            'delete blockages',
        ];
        $pipelineTaskPermissions = [
            'view pipeline tasks',
            'create pipeline tasks',
            'edit pipeline tasks',
            'delete pipeline tasks',
            'manage pipeline tasks',
            'update pipeline task status', // Ajouté pour route updatePipelineTaskStatus
            'move pipeline tasks between stages', // Ajouté pour route moveTaskToStage
            'view entity pipeline tasks', // Ajouté pour route getAllPipelineTasks
            'view stage pipeline tasks', // Ajouté pour route getPipelineTasks
        

        ];
        $invitePermissions = [
            'view invites',
            'create invites',
            'edit invites',
            'delete invites',
            'send invites',
        ];

        // Fusionner toutes les permissions
        $allPermissions = array_merge($taskPermissions, $userPermissions, $blockagePermissions, $pipelineTaskPermissions, $invitePermissions);

        // Créer les permissions (en évitant les doublons)
        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => $guard]);
        }

        // Créer le rôle admin et lui assigner toutes les permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
        $adminRole->syncPermissions($allPermissions);

        // Créer le rôle responsable fipa avec permissions limitées
        $responsablePermissions = [
            'view tasks',
            'create tasks',
            'edit tasks',
            'delete tasks',
            'view pipeline tasks',  
            'create pipeline tasks', 
            'edit pipeline tasks',
            'update pipeline task status',
            'view entity pipeline tasks',
            'view stage pipeline tasks',
        ];

        $responsableRole = Role::firstOrCreate(['name' => 'responsable fipa', 'guard_name' => $guard]);
        $responsableRole->syncPermissions($responsablePermissions);

        // Assigner les rôles aux utilisateurs existants (si trouvés)
        $admin = User::find(1);
        if ($admin && !$admin->hasRole('admin')) {
            $admin->assignRole($adminRole);
        }

        $responsable = User::find(2);
        if ($responsable && !$responsable->hasRole('responsable fipa')) {
            $responsable->assignRole($responsableRole);
        }
        $responsable = User::find(3);
        if ($responsable && !$responsable->hasRole('responsable fipa')) {
            $responsable->assignRole($responsableRole);
        }
    }
}
