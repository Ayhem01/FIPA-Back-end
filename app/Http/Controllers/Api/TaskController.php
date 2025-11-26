<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User; 
use App\Http\Requests\TaskRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Helpers\AuthorizationHelper;
use App\Services\PipelineTaskService;
use App\Services\BlockchainService;
use App\Models\BlockchainTransaction;

class TaskController extends Controller
{
  
/**
 * Afficher la liste des tâches avec filtres optionnels
 */
public function index(Request $request): JsonResponse
{
    $user = Auth::user();
    $userId = $user->id;

    \Log::info('Filtres de tâches reçus:', $request->all());

    // Charger relations utilisateur & assigné
    $query = Task::query()->with(['user:id,name', 'assignee:id,name']);

    /**
     * ----------------------------------------------------
     *  🔐 1) RESTRICTION SELON LE RÔLE (ADMIN OU PAS)
     * ----------------------------------------------------
     */

    $isAdmin = $user->hasRole('admin') || $user->can('manage all tasks');

    if (!$isAdmin) {
        // Utilisateur normal → que ses propres tâches (créées ou assignées)
        $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('assignee_id', $userId);
        });
    }

    /**
     * ----------------------------------------------------
     *  🎛️ 2) FILTRES STANDARD
     * ----------------------------------------------------
     */

    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    if ($request->filled('priority')) {
        $query->where('priority', $request->priority);
    }

    if ($request->filled('type')) {
        $query->where('type', $request->type);
    }

    if ($request->filled('start_date') && $request->filled('end_date')) {
        $query->whereBetween('start', [
            $request->start_date,
            $request->end_date
        ]);
    }

    /**
     * ----------------------------------------------------
     *  🔎 3) FILTRES AVANCÉS
     * ----------------------------------------------------
     */

    if ($request->filled('user_or_assignee_id')) {
        $query->where(function ($q) use ($request) {
            $q->where('user_id', $request->user_or_assignee_id)
              ->orWhere('assignee_id', $request->user_or_assignee_id);
        });
    }

    if ($request->filled('user_id')) {
        $query->where('user_id', $request->user_id);
    }

    if ($request->filled('assignee_id')) {
        $query->where('assignee_id', $request->assignee_id);
    }

    if ($request->filled('exclude_user_id')) {
        $query->where('user_id', '!=', $request->exclude_user_id);
    }

    /**
     * ----------------------------------------------------
     *  📊 4) TRI & PAGINATION
     * ----------------------------------------------------
     */

    $sortField = $request->get('sort_field', 'created_at');
    $sortAscDesc = $request->get('sort_direction', 'desc');
    $perPage = $request->get('per_page', 10);

    \Log::info('SQL:', ['sql' => $query->toSql(), 'bindings' => $query->getBindings()]);

    $tasks = $query->orderBy($sortField, $sortAscDesc)->paginate($perPage);

    return response()->json([
        'status' => 'success',
        'data' => $tasks,
        'message' => 'Tâches récupérées avec succès'
    ]);
}

    
    /**
     * Créer une nouvelle tâche
     */
    public function store(TaskRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $userId = Auth::id();
            $user = Auth::user();
            
            // Définir l'utilisateur courant comme créateur
            $validated['user_id'] = $userId;
            
            // Gestion de l'assignation selon le rôle
            if ($this->userHasRole('admin')) {
                // Admin doit spécifier un utilisateur à qui assigner la tâche
                if (!isset($validated['assignee_id'])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'En tant qu\'administrateur, vous devez assigner cette tâche à un responsable'
                    ], 422);
                }
                
                // Vérifier que l'utilisateur assigné existe et a le rôle "responsable fipa"
                $assignee = User::find($validated['assignee_id']);
                if (!$assignee) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'L\'utilisateur assigné n\'existe pas'
                    ], 422);
                }
                
                if (!$assignee->hasRole('responsable fipa')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Vous ne pouvez assigner des tâches qu\'aux utilisateurs ayant le rôle "responsable fipa"'
                    ], 422);
                }
            } else {
                // Pour les responsables fipa, l'assignation est toujours à eux-mêmes
                // CORRECTION: assignee_id doit être l'ID de l'utilisateur, pas null
                $validated['assignee_id'] = $userId;
            }
            
            // Définir la couleur en fonction du type si non spécifiée
            if (!isset($validated['color'])) {
                $validated['color'] = $this->getColorByType($validated['type']);
            }
            
            $task = Task::create($validated);
            
            return response()->json([
                'status' => 'success',
                'data' => $task->load(['user:id,name', 'assignee:id,name']),
                'message' => 'Tâche créée avec succès'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la création de tâche', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors de la création de la tâche',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Récupérer les détails d'une tâche spécifique
     */
    public function show($id): JsonResponse
    {
        try {
            $task = Task::findOrFail($id);
            $userId = Auth::id();
            
            // Vérification des autorisations avec la méthode sécurisée
            $isAuthorized = 
                $this->userHasRole('admin') || 
                $this->userCan('manage all tasks') ||
                $task->user_id == $userId || 
                $task->assignee_id == $userId;
                
            if (!$isAuthorized) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vous n\'êtes pas autorisé à accéder à cette tâche'
                ], 403);
            }

            $task->load(['user:id,name', 'assignee:id,name']);
            
            return response()->json([
                'status' => 'success',
                'data' => $task,
                'message' => 'Détails de la tâche récupérés avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération de la tâche', [
                'task_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Tâche non trouvée ou erreur lors de la récupération',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }
    public function showPipelineTask($taskId, PipelineTaskService $pipelineTaskService)
{
    try {
        // Vérifier que la tâche existe
        $task = Task::whereNotNull('pipeline_stage_id')->find($taskId);
        
        if (!$task) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tâche non trouvée'
            ], 404);
        }
        
        // Désactiver temporairement la vérification pour déboguer
        $bypassAuth = true; // TEMPORAIRE
        
        // Vérifier les autorisations
        $userId = Auth::id();
        $isAuthorized = 
            $bypassAuth || // Bypass temporaire 
            $task->user_id == $userId || 
            $task->assignee_id == $userId ||
            $this->userHasRole('admin') || 
            $this->userCan('view pipeline tasks') ||
            $this->userCan('view entity pipeline tasks') ||
            $this->userCan('view stage pipeline tasks') ||
            $this->userCan('manage pipeline tasks');
        
        // Journalisation pour déboguer
        \Log::info('Vérification d\'autorisation pour tâche #' . $taskId, [
            'user_id' => $userId,
            'task_user_id' => $task->user_id,
            'task_assignee_id' => $task->assignee_id,
            'is_admin' => $this->userHasRole('admin'),
            'can_view_pipeline_tasks' => $this->userCan('view pipeline tasks'),
            'can_view_entity_pipeline_tasks' => $this->userCan('view entity pipeline tasks'),
            'can_view_stage_pipeline_tasks' => $this->userCan('view stage pipeline tasks'),
            'can_manage_pipeline_tasks' => $this->userCan('manage pipeline tasks'),
            'result' => $isAuthorized
        ]);
        
        if (!$isAuthorized) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vous n\'êtes pas autorisé à consulter cette tâche'
            ], 403);
        }

        // Charger les relations supplémentaires pour obtenir des informations complètes
        $task->load([
            'user:id,name,email',
            'assignee:id,name,email',
        ]);
        
        // Récupérer des informations supplémentaires sur l'entité associée
        $entityInfo = null;
        if ($task->entity_type && $task->entity_id) {
            $entity = $pipelineTaskService->getEntityByType($task->entity_type, $task->entity_id);
            if ($entity) {
                $entityInfo = [
                    'type' => $task->entity_type,
                    'id' => $task->entity_id,
                    'name' => $entity->nom ?? $entity->title ?? $entity->name ?? null,
                    'description' => $entity->description ?? null,
                ];
            }
        }
        
        // Récupérer des informations sur l'étape du pipeline
        $stageInfo = null;
        if ($task->pipeline_stage_id) {
            $stage = $pipelineTaskService->getStageByType($task->entity_type, $task->pipeline_stage_id);
            if ($stage) {
                $stageInfo = [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'order' => $stage->order,
                    'is_final' => $stage->is_final ?? false,
                ];
            }
        }
        
        // Construire la réponse enrichie
        $response = [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'type' => $task->type,
            'status' => $task->status,
            'priority' => $task->priority,
            'color' => $task->color,
            'start' => $task->start,
            'end' => $task->end,
            'created_at' => $task->created_at,
            'updated_at' => $task->updated_at,
            'creator' => $task->user,
            'assignee' => $task->assignee,
            'entity' => $entityInfo,
            'pipeline_stage' => $stageInfo,
        ];
        
        return response()->json([
            'status' => 'success',
            'data' => $response,
            'message' => 'Détails de la tâche récupérés avec succès'
        ]);
    } catch (\Exception $e) {
        \Log::error('Erreur lors de la récupération des détails de la tâche: ' . $e->getMessage(), [
            'task_id' => $taskId,
            'user_id' => Auth::id(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'status' => 'error',
            'message' => 'Une erreur est survenue',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

    /**
     * Mettre à jour une tâche existante
     */
    public function update(TaskRequest $request, $id): JsonResponse
    {
        try {
            $task = Task::findOrFail($id);
            $userId = Auth::id();
            $user = Auth::user();
            
            // Seul le créateur peut modifier la tâche
            if ($task->user_id !== $userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vous n\'êtes pas le créateur de cette tâche et ne pouvez pas la modifier'
                ], 403);
            }
    
            $validated = $request->validated();
            
            // Protection supplémentaire: les responsables ne peuvent pas changer l'assignee_id
            if ($this->userHasRole('responsable fipa') && isset($validated['assignee_id']) && $validated['assignee_id'] != $task->assignee_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vous n\'êtes pas autorisé à modifier l\'assignation de cette tâche'
                ], 403);
            }
            
            // Mettre à jour la couleur si le type a changé et que la couleur n'est pas spécifiée
            if (isset($validated['type']) && !isset($validated['color'])) {
                $validated['color'] = $this->getColorByType($validated['type']);
            }
            
            $task->update($validated);
            
            return response()->json([
                'status' => 'success',
                'data' => $task->fresh(['user:id,name', 'assignee:id,name']),
                'message' => 'Tâche mise à jour avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour de tâche', [
                'task_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors de la mise à jour de la tâche',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Supprimer une tâche
     */
    public function destroy($id): JsonResponse
    {
        try {
            $task = Task::findOrFail($id);
            $userId = Auth::id();
            
            // Vérifier si l'utilisateur a le droit de supprimer cette tâche
            // Soit le créateur, soit un admin
            $isAuthorized = 
                ($task->user_id == $userId) || 
                ($this->userHasRole('admin') && $this->userCan('delete tasks'));
                
            if (!$isAuthorized) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vous n\'êtes pas autorisé à supprimer cette tâche'
                ], 403);
            }

            $task->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Tâche supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la suppression de tâche', [
                'task_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors de la suppression de la tâche',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Mettre à jour uniquement le statut d'une tâche
     */
    public function updateStatus(Request $request, Task $task): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            // Vérifier si l'utilisateur a le droit de modifier cette tâche
            // Soit le créateur, soit l'assigné, soit un admin
            $isAuthorized = 
                $task->user_id == $userId || 
                $task->assignee_id == $userId ||
                $this->userHasRole('admin') || 
                $this->userCan('manage all tasks');
                
            if (!$isAuthorized) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vous n\'êtes pas autorisé à modifier le statut de cette tâche'
                ], 403);
            }

            $request->validate([
                'status' => ['required', 'string', 'in:not_started,in_progress,completed,deferred,waiting']
            ]);
            
            $task->update(['status' => $request->status]);
            
            return response()->json([
                'status' => 'success',
                'data' => $task->only(['id', 'status']),
                'message' => 'Statut mis à jour avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour du statut', [
                'task_id' => $task->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors de la mise à jour du statut',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    
/**
 * Récupérer les tâches pour le calendrier
 */
public function getCalendarTasks(Request $request): JsonResponse
{
    try {
        $userId = Auth::id();
        
        // Filtrage par période
        $startDate = $request->get('start', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end', Carbon::now()->endOfMonth()->format('Y-m-d'));
        
        // Appliquer la restriction d'utilisateur (par défaut uniquement ses tâches assignées ou créées)
        $query = Task::where(function($query) use ($userId) {
            $query->where('user_id', $userId)
                  ->orWhere('assignee_id', $userId);
        })->where(function($query) use ($startDate, $endDate) {
            $query->whereBetween('start', [$startDate, $endDate])
                  ->orWhereBetween('end', [$startDate, $endDate]);
        })->with(['assignee:id,name']);
        
        // Exception : si admin ou permission manage all tasks → voir toutes les tâches
        if ($this->userHasRole('admin') || $this->userCan('manage all tasks')) {
            $query = Task::where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('start', [$startDate, $endDate])
                      ->orWhereBetween('end', [$startDate, $endDate]);
            })->with(['assignee:id,name']);
        }
                      
        // Filtres additionnels
        if ($request->filled('assignee_id') && ($this->userHasRole('admin') || $this->userCan('manage all tasks'))) {
            $query->where('assignee_id', $request->assignee_id);
        }
        
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Nouveau : filtrage par entity_type
        if ($request->filled('entity_type') && $request->entity_type !== 'all') {
            $query->where('entity_type', $request->entity_type);
        }
        
        $tasks = $query->get();
        
        // Formatage pour FullCalendar
        $formattedTasks = $tasks->map(function($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'start' => $task->start->format('Y-m-d H:i:s'),
                'end' => $task->end ? $task->end->format('Y-m-d H:i:s') : null,
                'allDay' => $task->all_day,
                'color' => $task->color,
                'className' => $task->type,
                'extendedProps' => [
                    'type' => $task->type,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'description' => $task->description,
                    'entity_type' => $task->entity_type,
                    'entity_id' => $task->entity_id,
                    'assignee' => $task->assignee ? [
                        'id' => $task->assignee->id,
                        'name' => $task->assignee->name
                    ] : null
                ]
            ];
        });
        
        return response()->json($formattedTasks);
    } catch (\Exception $e) {
        Log::error('Erreur lors de la récupération des tâches du calendrier', [
            'user_id' => Auth::id(),
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'status' => 'error',
            'message' => 'Une erreur est survenue lors de la récupération des tâches du calendrier',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}
    

    /**
     * Déplacer une tâche dans le calendrier (gestion du glisser-déposer)
     */
    public function moveTask(Request $request, Task $task): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            // Vérifier si l'utilisateur a le droit de modifier cette tâche
            $isAuthorized = 
                $task->user_id == $userId || 
                $task->assignee_id == $userId ||
                $this->userHasRole('admin') || 
                $this->userCan('manage all tasks');
                
            if (!$isAuthorized) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vous n\'êtes pas autorisé à déplacer cette tâche'
                ], 403);
            }
            
            $request->validate([
                'start' => 'required|date',
                'end' => 'nullable|date|after_or_equal:start',
                'allDay' => 'boolean'
            ]);
            
            $updateData = [
                'start' => $request->start,
                'end' => $request->end,
                'all_day' => $request->allDay
            ];
            
            $task->update($updateData);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => $task->id,
                    'start' => $task->start->format('Y-m-d H:i:s'),
                    'end' => $task->end ? $task->end->format('Y-m-d H:i:s') : null,
                    'allDay' => $task->all_day
                ],
                'message' => 'Tâche déplacée avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors du déplacement de la tâche', [
                'task_id' => $task->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors du déplacement de la tâche',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Récupérer mes tâches (assignées à l'utilisateur connecté)
     */
    public function getMyTasks(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $query = Task::where('assignee_id', $userId)
                         ->with(['user:id,name']);
            
            // Filtres
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('priority')) {
                $query->where('priority', $request->priority);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }
            
            // Tri
            $sortField = $request->get('sort_field', 'start');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortField, $sortDirection);
            
            // Pagination
            $perPage = $request->get('per_page', 10);
            $tasks = $query->paginate($perPage);
            
            return response()->json([
                'status' => 'success',
                'data' => $tasks,
                'message' => 'Mes tâches récupérées avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des tâches personnelles', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors de la récupération de vos tâches',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Récupérer les statistiques pour le tableau de bord
     */
    public function getDashboardStats(): JsonResponse
{
    try {
        $user = Auth::user();
        $userId = $user?->id;

        $isAdmin = $this->userHasRole('admin') || $this->userCan('manage all tasks');

        $base = Task::query();

        // Portée des données
        if (!$isAdmin) {
            if (!$userId) {
                return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
            }
            $base->where('assignee_id', $userId);
        } else {
            // Admin: filtre optionnel par assigné
            if (request()->filled('assignee_id')) {
                $base->where('assignee_id', request()->get('assignee_id'));
            }
        }

        $today = Carbon::now()->startOfDay();
        $weekStart = Carbon::now()->startOfDay();
        $weekEnd = Carbon::now()->addDays(7)->endOfDay();

        // Compteurs
        $totalTasks      = (clone $base)->count();
        $completedTasks  = (clone $base)->where('status', 'completed')->count();
        $inProgressTasks = (clone $base)->where('status', 'in_progress')->count();
        $notStartedTasks = (clone $base)->where('status', 'not_started')->count();

        // Overdue et à venir
        $overdueTasks = (clone $base)
            ->where('end', '<', $today)
            ->whereNotIn('status', ['completed', 'deferred'])
            ->count();

        $upcomingTasks = (clone $base)
            ->whereBetween('start', [$weekStart, $weekEnd])
            ->whereNotIn('status', ['completed', 'deferred'])
            ->count();

        // Groupes
        $tasksByType = (clone $base)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get();

        $tasksByStatus = (clone $base)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        // Récentes
        $recentTasks = (clone $base)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'title', 'type', 'status', 'priority', 'start', 'end']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'scope' => $isAdmin ? 'global' : 'user',
                'assignee_filter' => $isAdmin ? request()->get('assignee_id') : $userId,
                'total' => $totalTasks,
                'completed' => $completedTasks,
                'inProgress' => $inProgressTasks,
                'notStarted' => $notStartedTasks,
                'overdue' => $overdueTasks,
                'upcoming' => $upcomingTasks,
                'byType' => $tasksByType,
                'byStatus' => $tasksByStatus,
                'recentTasks' => $recentTasks
            ],
            'message' => 'Statistiques récupérées avec succès'
        ]);
    } catch (\Exception $e) {
        Log::error('Erreur lors de la récupération des statistiques', [
            'user_id' => Auth::id(),
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Une erreur est survenue lors de la récupération des statistiques',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

    /**
     * Récupérer les tâches de l'utilisateur connecté (créées ou assignées)
     */
    public function getUserTasks(): JsonResponse
    {
        try {
            $userId = Auth::id();
            $user = Auth::user();
            
            // Récupérer les tâches créées par l'utilisateur OU assignées à l'utilisateur
            $tasks = Task::where('user_id', $userId)
                        ->orWhere('assignee_id', $userId)
                        ->with(['user:id,name', 'assignee:id,name'])
                        ->orderBy('created_at', 'desc')
                        ->get();
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'tasks' => $tasks,
                    'user_info' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'roles' => $this->getUserRoles(),
                        'permissions' => $this->getUserPermissions()
                    ]
                ],
                'message' => 'Tâches récupérées avec succès'
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des tâches utilisateur', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors de la récupération des tâches',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtenir une couleur basée sur le type de tâche
     */
    private function getColorByType(string $type): string
    {
        return match($type) {
            'call' => '#1890ff',
            'meeting' => '#52c41a',
            'email_journal' => '#eb2f96',
            'note' => '#722ed1',
            'todo' => '#faad14',
            default => '#1890ff',
        };
    }
    
    /**
     * Vérifier en toute sécurité si l'utilisateur a un rôle spécifique
     */
    private function userHasRole($role): bool
    {
        if (!Auth::check()) {
            return false;
        }
        
        try {
            return Auth::user()->hasRole($role);
        } catch (\Exception $e) {
            Log::warning('Erreur lors de la vérification du rôle: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifier en toute sécurité si l'utilisateur a une permission spécifique
     */
    private function userCan($permission): bool
    {
        if (!Auth::check()) {
            return false;
        }
        
        try {
            return Auth::user()->can($permission);
        } catch (\Exception $e) {
            Log::warning('Erreur lors de la vérification de permission: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer les permissions utilisateur avec gestion d'erreur
     */
    private function getUserPermissions()
    {
        if (!Auth::check()) {
            return [];
        }
        
        try {
            return Auth::user()->getPermissionNames();
        } catch (\Exception $e) {
            Log::warning('Erreur lors de la récupération des permissions: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer les rôles utilisateur avec gestion d'erreur
     */
    private function getUserRoles()
    {
        if (!Auth::check()) {
            return [];
        }
        
        try {
            return Auth::user()->getRoleNames();
        } catch (\Exception $e) {
            Log::warning('Erreur lors de la récupération des rôles: ' . $e->getMessage());
            return [];
        }
    }
 
    
    public function getTasksByPipelineStage(Request $request, PipelineTaskService $pipelineTaskService): JsonResponse
    {
        try {
            $validator = Validator::make($request->query(), [ // ✅ on valide depuis la query string
                'stage_id' => 'required|integer',
                'entity_id' => 'required|integer',
                'entity_type' => 'required|string|in:invite,prospect,investor,projet'
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }
            $entityType = $request->query('entity_type');
            if ($entityType === 'investisseur') {
                $entityType = 'investor';
            }
            
            // Vérifier le type après normalisation
            if (!in_array($entityType, ['invite', 'prospect', 'investor', 'projet'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Type d\'entité non valide'
                ], 400);
            }
    
            $tasks = $pipelineTaskService->getTasksForStage(
                $request->query('entity_type'),
                $request->query('entity_id'),
                $request->query('stage_id')
            );
    
            return response()->json([
                'status' => 'success',
                'data' => $tasks,
                'message' => "Tâches de l'étape récupérées avec succès"
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur récupération tâches étape: ' . $e->getMessage());
    
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    

    public function getPipelineTasks($entityType, $entityId, $stageId, PipelineTaskService $pipelineTaskService)
{
    try {
        if ($entityType === 'investisseur') {
            $entityType = 'investor';
        }
        if ($entityType === 'project') {
            $entityType = 'projet';
        }
        


        if (!in_array($entityType, ['invite', 'prospect', 'investor', 'projet'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Type d\'entité non valide'
            ], 400);
        }

        $tasks = $pipelineTaskService->getTasksForStage($entityType, $entityId, $stageId);

        return response()->json([
            'status' => 'success',
            'data' => $tasks,
            'message' => "Tâches de l'étape {$stageId} pour {$entityType} #{$entityId} récupérées avec succès"
        ]);
    } catch (\Exception $e) {
        \Log::error('Erreur récupération tâches pipeline: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Erreur lors de la récupération des tâches',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

    /**
     * Récupérer toutes les tâches d'un pipeline pour une entité
     */
    public function getAllPipelineTasks($entityType, $entityId, PipelineTaskService $pipelineTaskService)
    {
        try {
            if ($entityType === 'investisseur') {
                $entityType = 'investor';
            }
            if (!in_array($entityType, ['invite', 'prospect', 'investor', 'projet'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Type d\'entité non valide'
                ], 400);
            }
    
            $tasks = $pipelineTaskService->getAllPipelineTasks($entityType, $entityId);
    
            return response()->json([
                'status' => 'success',
                'data' => $tasks,
                'message' => "Toutes les tâches de l'entité {$entityType} #{$entityId} récupérées avec succès"
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur récupération tâches pipeline: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des tâches',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

 
    public function updatePipelineTask(Request $request, $taskId, PipelineTaskService $pipelineTaskService)
    {
        try {
            // Validation des données
            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|nullable|string',
                'start' => 'sometimes|required|date',
                'end' => 'sometimes|nullable|date|after_or_equal:start',
                'type' => 'sometimes|required|in:call,meeting,email_journal,note,todo',
                'priority' => 'sometimes|nullable|in:low,medium,high,urgent',
                'status' => 'sometimes|required|in:not_started,in_progress,completed,deferred,waiting',
                'assignee_id' => 'sometimes|nullable|exists:users,id',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Vérifier les autorisations
            $task = Task::find($taskId);
            if (!$task) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tâche non trouvée'
                ], 404);
            }
            
            // Désactiver temporairement la vérification pour déboguer
            $bypassAuth = true; // TEMPORAIRE
            
            // Vérifier les autorisations avec des conditions plus flexibles
            $userId = Auth::id();
            $isAuthorized = 
                $bypassAuth || // Bypass temporaire 
                $task->user_id == $userId || 
                $task->assignee_id == $userId ||
                $this->userHasRole('admin') || 
                $this->userCan('edit pipeline tasks') ||
                $this->userCan('manage pipeline tasks') ||
                $this->userCan('update pipeline task status');
            
            // Journalisation pour déboguer
            \Log::info('Vérification d\'autorisation pour mise à jour tâche #' . $taskId, [
                'user_id' => $userId,
                'task_user_id' => $task->user_id,
                'task_assignee_id' => $task->assignee_id,
                'is_admin' => $this->userHasRole('admin'),
                'can_edit_pipeline_tasks' => $this->userCan('edit pipeline tasks'),
                'can_manage_pipeline_tasks' => $this->userCan('manage pipeline tasks'),
                'can_update_status' => $this->userCan('update pipeline task status'),
                'result' => $isAuthorized
            ]);
                
            if (!$isAuthorized) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vous n\'êtes pas autorisé à modifier cette tâche'
                ], 403);
            }
    
            // Utiliser le service pour mettre à jour la tâche
            $updatedTask = $pipelineTaskService->updateTask($taskId, $request->all());
            
            return response()->json([
                'status' => 'success',
                'data' => $updatedTask,
                'message' => 'Tâche mise à jour avec succès'
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur mise à jour tâche pipeline: ' . $e->getMessage(), [
                'task_id' => $taskId,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * Mettre à jour le statut d'une tâche de pipeline
     */
    public function updatePipelineTaskStatus(Request $request, $taskId, PipelineTaskService $pipelineTaskService)
    {
        try {
            // Validation des données
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:not_started,in_progress,completed,deferred,waiting',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Vérifier les autorisations
            $task = Task::find($taskId);
            if (!$task) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tâche non trouvée'
                ], 404);
            }
            
            $userId = Auth::id();
            $isAuthorized = 
                $task->user_id == $userId || 
                $task->assignee_id == $userId ||
                $this->userHasRole('admin') || 
                $this->userCan('manage pipeline tasks');
                
            if (!$isAuthorized) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vous n\'êtes pas autorisé à modifier cette tâche'
                ], 403);
            }

            // Utiliser le service pour mettre à jour le statut
            $updatedTask = $pipelineTaskService->updateTaskStatus($taskId, $request->status);
            
            return response()->json([
                'status' => 'success',
                'data' => $updatedTask,
                'message' => 'Statut mis à jour avec succès'
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur mise à jour statut tâche pipeline: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    public function moveTaskToStage(Request $request, $taskId, PipelineTaskService $pipelineTaskService)
    {
        try {
            // Validation des données
            $validator = Validator::make($request->all(), [
                'stage_id' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Vérifier les autorisations
            $task = Task::find($taskId);
            if (!$task) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tâche non trouvée'
                ], 404);
            }
            
            $userId = Auth::id();
            $isAuthorized = 
                $task->user_id == $userId || 
                $task->assignee_id == $userId ||
                $this->userHasRole('admin') || 
                $this->userCan('manage pipeline tasks');
                
            if (!$isAuthorized) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vous n\'êtes pas autorisé à déplacer cette tâche'
                ], 403);
            }

            // Utiliser le service pour déplacer la tâche
            $movedTask = $pipelineTaskService->moveTaskToStage($taskId, $request->stage_id);
            
            return response()->json([
                'status' => 'success',
                'data' => $movedTask,
                'message' => 'Tâche déplacée avec succès'
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur déplacement tâche pipeline: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    

    public function deletePipelineTask($taskId, PipelineTaskService $pipelineTaskService)
    {
        try {
            // Vérifier les autorisations
            $task = Task::find($taskId);
            if (!$task) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tâche non trouvée'
                ], 404);
            }
            
            $userId = Auth::id();
            $isAuthorized = 
                $task->user_id == $userId || 
                $this->userHasRole('admin') || 
                $this->userCan('manage pipeline tasks');
                
            if (!$isAuthorized) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vous n\'êtes pas autorisé à supprimer cette tâche'
                ], 403);
            }

            // Utiliser le service pour supprimer la tâche
            $pipelineTaskService->deleteTask($taskId);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Tâche supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur suppression tâche pipeline: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
 

public function createPipelineTask(Request $request, $entityType, $entityId, $stageId, PipelineTaskService $pipelineTaskService)
{
    try {
        // Validation
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start' => 'required|date',
            'end' => 'nullable|date|after_or_equal:start',
            'type' => 'required|in:call,meeting,email_journal,note,todo',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'status' => 'nullable|in:not_started,in_progress,completed,deferred,waiting'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation échouée',
                'errors' => $validator->errors()
            ], 422);
        }

        // Normaliser le type d'entité
        if ($entityType === 'investisseur') {
            $entityType = 'investor';
        }
        if ($entityType === 'project') {
            $entityType = 'projet';
        }

        // Valider le type d'entité
        if (!in_array($entityType, ['invite', 'prospect', 'investor', 'projet'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Type d\'entité non valide'
            ], 400);
        }

        // ========================================
        // 1️⃣ BASE DE DONNÉES: CRÉER LA TÂCHE VIA LE SERVICE
        // ========================================
        $task = $pipelineTaskService->createTaskForStage(
            $entityType,
            $entityId,
            $stageId,
            $request->all()
        );

        // ========================================
        // 2️⃣ BLOCKCHAIN: ENREGISTRER LA TÂCHE
        // ========================================
        // Créer l'enregistrement blockchain PENDING
        $blockchainTx = BlockchainTransaction::create([
            'related_type' => 'task',
            'related_id' => $task->id,
            'action' => 'create_task',
            'status' => BlockchainTransaction::STATUS_PENDING,
            'request' => [
                'title' => $task->title,
                'description' => $task->description ?? '',
                'status' => $task->status ?? 'not_started',
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'created_by' => Auth::id()
            ]
        ]);

        try {
            $service = app(BlockchainService::class);
            
            \Log::info('📤 Envoi tâche pipeline vers blockchain', [
                'task_id' => $task->id,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'stage_id' => $stageId
            ]);
            
            $res = $service->createTaskOnChain(
                title: $task->title,
                description: $task->description ?? '',
                status: $task->status ?? 'not_started',
                entityId: (int)$entityId,
                entityType: $entityType,
                createdByUserId: Auth::id()
            );
            
            // Mettre à jour la TX avec les données blockchain
            $blockchainTx->update([
                'status' => BlockchainTransaction::STATUS_SUCCESS,
                'tx_hash' => $res['data']['transactionHash'] ?? null,
                'block_number' => $res['data']['blockNumber'] ?? null,
                'response' => $res
            ]);
            
            \Log::info('✅ Tâche pipeline créée sur blockchain', [
                'task_id' => $task->id,
                'task_id_chain' => $res['data']['taskId'] ?? null,
                'tx_hash' => $blockchainTx->tx_hash,
                'block_number' => $blockchainTx->block_number
            ]);
            
        } catch (\Throwable $e) {
            // Marquer la TX comme échouée
            $blockchainTx->update([
                'status' => BlockchainTransaction::STATUS_FAILED,
                'error' => $e->getMessage(),
                'response' => ['error' => $e->getMessage()]
            ]);
            
            \Log::warning('⚠️ Blockchain task creation failed (graceful degradation)', [
                'task_id' => $task->id,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage()
            ]);
            
            // Ne pas bloquer la création - la tâche existe déjà en DB
            // On continue et on retourne quand même la tâche créée
        }

        // Charger les relations pour la réponse
        $task->load(['user:id,name', 'assignee:id,name']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'task' => $task,
                'blockchain_info' => [
                    'status' => $blockchainTx->status,
                    'tx_hash' => $blockchainTx->tx_hash,
                    'block_number' => $blockchainTx->block_number,
                    'task_id_chain' => $blockchainTx->response['data']['taskId'] ?? null
                ]
            ],
            'message' => 'Tâche créée avec succès'
        ], 201);
        
    } catch (\Exception $e) {
        \Log::error('❌ Erreur création tâche pipeline', [
            'entity_type' => $entityType ?? null,
            'entity_id' => $entityId ?? null,
            'stage_id' => $stageId ?? null,
            'user_id' => Auth::id(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'status' => 'error',
            'message' => 'Une erreur est survenue lors de la création de la tâche',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}



}