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
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $guard = 'api';

        // ============== Permissions génériques CRUD helper ==============
        $crud = fn(string $name) => [
            "view {$name}", "create {$name}", "edit {$name}", "delete {$name}",
        ];

        // ============== Ressources simples (CRUD) ==============
        $resourcesCrud = [
            'media',
            'responsable bureau media',
            'vav siege media',
            'initiateurs',
            'pays',
            'secteurs',
            'cte',
            'binomes',
            'salons',
            'seminaires ji pays',
            'nationalites',
            'responsables fipa',
            'groupes',
            'delegations',
            'responsables suivi',
            'visites entreprises',
            'seminaires ji secteur',
            'salon sectoriel',
            'demarchage direct',
            'contacts',
            'project contacts',
        ];

        $simplePermissions = [];
        foreach ($resourcesCrud as $r) {
            $simplePermissions = array_merge($simplePermissions, $crud($r));
        }

        // ============== Entreprises ==============
        $entreprisePermissions = array_merge($crud('entreprises'), [
            'update entreprise pipeline stage',
            'view entreprise search',
            'view entreprise dashboard stats',
        ]);

        // ============== Users ==============
        $userPermissions = array_merge($crud('users'), [
            'assign user roles',
            'assign user permissions',
        ]);

        // ============== Actions ==============
        $actionPermissions = array_merge($crud('actions'), [
            'update action status',
            'view actions treemap',
            'view action calendar',
            'view actions by entreprise',
        ]);

        // ============== Etapes (d’actions) ==============
        $etapePermissions = array_merge($crud('etapes'), [
            'reorder etapes',
            'view etapes by action',
        ]);

        // ============== Invites ==============
        $invitePermissions = array_merge($crud('invites'), [
            'send invites',
            'update invite status',
            'initialize invite pipeline',
            'advance invite stage',
            'view invite pipeline',
            'convert invite to prospect',
            'view invite progression',
            'view invite stats',
            'view invite charts',
            'view invites by entreprise',
        ]);

        // ============== Tasks (génériques) ==============
        $taskPermissions = array_merge($crud('tasks'), [
            'move tasks',
            'update task status',
            'view calendar tasks',
            'view dashboard task stats',
            'view my tasks',
        ]);

        // ============== Pipeline Tasks (spécifiques) ==============
        $pipelineTaskPermissions = [
            'view pipeline tasks',
            'create pipeline tasks',
            'edit pipeline tasks',
            'delete pipeline tasks',
            'update pipeline task status',
            'move pipeline task',
            'view pipeline entity tasks',
            'view pipeline stage tasks',
        ];

        // ============== Projects ==============
        $projectPermissions = array_merge($crud('projects'), [
            'update project status',
            'initialize project pipeline',
            'advance project stage',
            'update project pipeline stage',
            'view project pipeline',
            'create project from investisseur',
            'view investisseur data for project',
            'view project analytics',
        ]);

        // ============== Pipeline Types ==============
        $pipelineTypePermissions = array_merge($crud('pipeline types'));

        // ============== Global Pipeline Stages ==============
        $globalPipelineStagePermissions = [
            'view pipeline stages',
            'view pipeline stage details',
            'create pipeline stages',
            'edit pipeline stages',
            'delete pipeline stages',
            'reorder pipeline stages',
        ];

        // ============== Stats (dashboard) ==============
        $statsPermissions = [
            'view stats',
        ];

        // ============== Prospects ==============
        $prospectPermissions = array_merge($crud('prospects'), [
            'update prospect status',
            'initialize prospect pipeline',
            'advance prospect stage',
            'view prospect pipeline',
            'convert prospect to investor',
            'view investor data for conversion',
            'view prospect charts',
            'view prospect on-chain',
            'view prospect on-chain tasks',
            'view prospect on-chain stage tasks',
            'view prospect on-chain progress',
        ]);

        // ============== Investisseurs ==============
        $investisseurPermissions = array_merge($crud('investisseurs'), [
            'update investisseur status',
            'initialize investisseur pipeline',
            'advance investisseur stage',
            'view investisseur pipeline',
            'convert investisseur to project',
            'view investisseur charts',
        ]);

        // ============== Blockages ==============
        $blockagePermissions = array_merge($crud('blockages'), [
            'resolve blockages',
            'escalate blockages',
            'view all blockages admin',
            'view blockages by stage',
        ]);

        // ============== Agrégation ==============
        $allPermissions = array_values(array_unique(array_merge(
            $simplePermissions,
            $entreprisePermissions,
            $userPermissions,
            $actionPermissions,
            $etapePermissions,
            $invitePermissions,
            $taskPermissions,
            $pipelineTaskPermissions,
            $projectPermissions,
            $pipelineTypePermissions,
            $globalPipelineStagePermissions,
            $statsPermissions,
            $prospectPermissions,
            $investisseurPermissions,
            $blockagePermissions
        )));

        foreach ($allPermissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]);
        }

        // ============== Rôles ==============
        // Admin: tout
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => $guard]);
        $adminRole->syncPermissions($allPermissions);

        // Responsable FIPA: restrictions
        $responsableAllowed = array_values(array_diff($allPermissions, [
            // Entreprises: pas de gestion
            'create entreprises','edit entreprises','delete entreprises','update entreprise pipeline stage',

            // Actions: pas de création/édition/suppression/MAJ statut
            'create actions','edit actions','delete actions','update action status',

            // Users: aucune gestion
            'view users','create users','edit users','delete users','assign user roles','assign user permissions',

            // Global pipeline stages: pas de gestion
            'create pipeline stages','edit pipeline stages','delete pipeline stages','reorder pipeline stages',
        ]));

        $responsableRole = Role::firstOrCreate(['name' => 'responsable fipa', 'guard_name' => $guard]);
        $responsableRole->syncPermissions($responsableAllowed);

        // ============== Assignation par défaut (optionnelle) ==============
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

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}