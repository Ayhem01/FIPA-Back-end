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
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Helpers\AuthorizationHelper;

class TaskController extends Controller
{
  
/**
 * Afficher la liste des tâches avec filtres optionnels
 */
public function index(Request $request): JsonResponse
{
    $user = Auth::user();
    $userId = $user->id;

    // Log pour déboguer les paramètres reçus
    \Log::info('Filtres de tâches reçus:', $request->all());
    
    $query = Task::query()->with(['user:id,name', 'assignee:id,name']);
    
    // Si ce n'est pas un admin, restreindre aux tâches créées ou assignées à l'utilisateur
    if (!$this->userHasRole('admin') && !$this->userCan('manage all tasks')) {
        $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('assignee_id', $userId);
        });
    }
    
    // ✅ Filtrage standard
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
        $query->whereBetween('start', [$request->start_date, $request->end_date]);
    }
    
    // ✅ Filtres avancés (pour tous)
    // IMPORTANT: Appliquer les filtres dans le bon ordre pour éviter les conflits
    
    // Gérer les filtres user_id, assignee_id et exclude_user_id de manière exclusive
    // pour éviter des conditions contradictoires
    
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
        $excludeUserId = $request->exclude_user_id;
        $query->where('user_id', '!=', $excludeUserId);
    }
    
    
    
    // ✅ Tri et pagination
    $sortField = $request->get('sort_field', 'created_at');
    $sortDirection = $request->get('sort_direction', 'desc');
    $perPage = $request->get('per_page', 10);
    
    // Log SQL pour déboguer
    \Log::info('Requête SQL:', ['sql' => $query->toSql(), 'bindings' => $query->getBindings()]);
    
    $tasks = $query->orderBy($sortField, $sortDirection)->paginate($perPage);
    
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
            
            // Appliquer la restriction d'utilisateur
            $query = Task::where(function($query) use ($userId) {
                $query->where('user_id', $userId)
                      ->orWhere('assignee_id', $userId);
            })->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('start', [$startDate, $endDate])
                      ->orWhereBetween('end', [$startDate, $endDate]);
            })->with(['assignee:id,name']);
            
            // Exception pour les admins
            if ($this->userHasRole('admin') || $this->userCan('manage all tasks')) {
                $query = Task::where(function($query) use ($startDate, $endDate) {
                    $query->whereBetween('start', [$startDate, $endDate])
                          ->orWhereBetween('end', [$startDate, $endDate]);
                })->with(['assignee:id,name']);
            }
                          
            // Filtres additionnels
            if ($request->has('assignee_id') && ($this->userHasRole('admin') || $this->userCan('manage all tasks'))) {
                $query->where('assignee_id', $request->assignee_id);
            }
            
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
            
            if ($request->has('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }
            
            $tasks = $query->get();
            
            // Formatage pour le calendrier FullCalendar
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
            $userId = Auth::id();
            
            // Statistiques globales
            $totalTasks = Task::where('assignee_id', $userId)->count();
            $completedTasks = Task::where('assignee_id', $userId)->where('status', 'completed')->count();
            $inProgressTasks = Task::where('assignee_id', $userId)->where('status', 'in_progress')->count();
            $notStartedTasks = Task::where('assignee_id', $userId)->where('status', 'not_started')->count();
            
            // Tâches en retard
            $today = Carbon::now()->startOfDay();
            $overdueTasks = Task::where('assignee_id', $userId)
                                ->where('end', '<', $today)
                                ->whereNotIn('status', ['completed', 'deferred'])
                                ->count();
            
            // Tâches à venir cette semaine
            $weekStart = Carbon::now()->startOfDay();
            $weekEnd = Carbon::now()->addDays(7)->endOfDay();
            $upcomingTasks = Task::where('assignee_id', $userId)
                                 ->whereBetween('start', [$weekStart, $weekEnd])
                                 ->whereNotIn('status', ['completed', 'deferred'])
                                 ->count();
            
            // Répartition par type
            $tasksByType = Task::where('assignee_id', $userId)
                              ->select('type', DB::raw('count(*) as count'))
                              ->groupBy('type')
                              ->get();
            
            // Répartition par statut
            $tasksByStatus = Task::where('assignee_id', $userId)
                               ->select('status', DB::raw('count(*) as count'))
                               ->groupBy('status')
                               ->get();
            
            // Tâches récentes
            $recentTasks = Task::where('assignee_id', $userId)
                              ->orderBy('created_at', 'desc')
                              ->limit(5)
                              ->get(['id', 'title', 'type', 'status', 'priority', 'start', 'end']);
            
            return response()->json([
                'status' => 'success',
                'data' => [
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
                        ->with(['user:id,name,avatar', 'assignee:id,name,avatar'])
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
}