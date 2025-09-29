<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\InvitePipelineStage;
use App\Models\ProspectPipelineStage;
use App\Models\InvestorPipelineStage;
use App\Models\ProjectPipelineStage;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PipelineStageController extends Controller
{
    /**
     * Obtenir le modèle d'étape correspondant au type d'entité
     *
     * @param string $entityType Type d'entité (invite, prospect, investisseur, projet)
     * @return string Classe du modèle
     * @throws \Exception
     */
    protected function getModel($entityType)
    {
        return match ($entityType) {
            'invite'       => InvitePipelineStage::class,
            'prospect'     => ProspectPipelineStage::class,
            'investisseur', 'investor' => InvestorPipelineStage::class,
            'projet', 'project' => ProjectPipelineStage::class,
            default        => throw new \Exception("Type d'entité non supporté: $entityType"),
        };
    }

    /**
     * Récupérer toutes les étapes du pipeline pour un type d'entité
     *
     * @param string $entityType Type d'entité
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($entityType)
    {
        try {
            $model = $this->getModel($entityType);
            $stages = $model::orderBy('order')->get();
            
            return response()->json([
                'success' => true,
                'data' => $stages
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur lors de la récupération des étapes: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer une nouvelle étape de pipeline
     *
     * @param Request $request
     * @param string $entityType Type d'entité
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $entityType)
    {
        try {
            // Validation
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'order' => 'nullable|integer|min:1',
                'color' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            $model = $this->getModel($entityType);

            return DB::transaction(function () use ($request, $model) {
                // Déterminer l'ordre (dernier + 1 par défaut)
                $order = $request->order ?? ($model::max('order') + 1);

                // Décaler les étapes existantes
                $model::where('order', '>=', $order)->increment('order');

                // Créer l'étape
                $stage = $model::create([
                    'name'        => $request->name,
                    'description' => $request->description,
                    'slug'        => Str::slug($request->name),
                    'order'       => $order,
                    'is_final'    => false,
                    'color'       => $request->color ?? '#1890ff',
                    'created_by'  => Auth::id(),
                ]);

                // Mettre à jour le flag is_final
                $this->fixFinalStage($model);

                return response()->json([
                    'success' => true, 
                    'message' => 'Étape créée avec succès',
                    'data' => $stage
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur lors de la création de l'étape: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour une étape existante
     *
     * @param Request $request
     * @param string $entityType Type d'entité
     * @param int $id ID de l'étape
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $entityType, $id)
    {
        try {
            // Validation
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'order' => 'nullable|integer|min:1',
                'color' => 'nullable|string|max:20',
                'is_active' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            $model = $this->getModel($entityType);

            return DB::transaction(function () use ($request, $model, $id) {
                $stage = $model::findOrFail($id);
                $oldOrder = $stage->order;
                $newOrder = $request->order ?? $oldOrder;

                // Réorganiser les étapes si l'ordre a changé
                if ($newOrder != $oldOrder) {
                    if ($newOrder < $oldOrder) {
                        $model::whereBetween('order', [$newOrder, $oldOrder - 1])->increment('order');
                    } else {
                        $model::whereBetween('order', [$oldOrder + 1, $newOrder])->decrement('order');
                    }
                }

                // Mettre à jour l'étape
                $stage->update([
                    'name'        => $request->name ?? $stage->name,
                    'description' => $request->description ?? $stage->description,
                    'order'       => $newOrder,
                    'color'       => $request->color ?? $stage->color,
                    'is_active'   => $request->has('is_active') ? $request->is_active : $stage->is_active,
                    'slug'        => $request->name ? Str::slug($request->name) : $stage->slug,
                ]);

                // Mettre à jour le flag is_final
                $this->fixFinalStage($model);

                return response()->json([
                    'success' => true, 
                    'message' => 'Étape mise à jour avec succès',
                    'data' => $stage->fresh()
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur lors de la mise à jour de l'étape: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer une étape
     *
     * @param string $entityType Type d'entité
     * @param int $id ID de l'étape
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($entityType, $id)
    {
        try {
            $model = $this->getModel($entityType);
            
            // Vérifier s'il y a des entités à cette étape
            // Cette vérification dépend de votre modèle de données
            
            return DB::transaction(function () use ($model, $id) {
                $stage = $model::findOrFail($id);
                $deletedOrder = $stage->order;
                
                // Vérifier si c'est la seule étape
                if ($model::count() <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => "Impossible de supprimer la dernière étape du pipeline"
                    ], 422);
                }
                
                $stage->delete();

                // Réorganiser les étapes restantes
                $model::where('order', '>', $deletedOrder)->decrement('order');

                // Mettre à jour le flag is_final
                $this->fixFinalStage($model);

                return response()->json([
                    'success' => true,
                    'message' => 'Étape supprimée avec succès'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur lors de la suppression de l'étape: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Réordonner les étapes de pipeline
     * 
     * @param Request $request
     * @param string $entityType Type d'entité
     * @return \Illuminate\Http\JsonResponse
     */
    public function reorder(Request $request, $entityType)
    {
        try {
            // Validation
            $validator = Validator::make($request->all(), [
                'stages' => 'required|array',
                'stages.*.id' => 'required|integer|exists:'.strtolower($entityType).'_pipeline_stages,id',
                'stages.*.order' => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            $model = $this->getModel($entityType);

            return DB::transaction(function () use ($request, $model) {
                foreach ($request->stages as $stageData) {
                    $stage = $model::find($stageData['id']);
                    if ($stage) {
                        $stage->update(['order' => $stageData['order']]);
                    }
                }

                // Mettre à jour le flag is_final
                $this->fixFinalStage($model);

                // Retourner les étapes réordonnées
                $stages = $model::orderBy('order')->get();

                return response()->json([
                    'success' => true,
                    'message' => 'Étapes réordonnées avec succès',
                    'data' => $stages
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur lors de la réorganisation des étapes: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir une étape spécifique
     * 
     * @param string $entityType Type d'entité
     * @param int $id ID de l'étape
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($entityType, $id)
    {
        try {
            $model = $this->getModel($entityType);
            $stage = $model::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $stage
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur lors de la récupération de l'étape: " . $e->getMessage()
            ], 500);
        }
    }


    public function getStageDetails($entityType, $entityId, $stageId)
    {
        try {
            // Modèle des stages
            $stageModel = $this->getModel($entityType);
    
            // Modèle de l'entité
            $entityModel = match ($entityType) {
                'invite'                  => \App\Models\Invite::class,
                'prospect'                => \App\Models\Prospect::class,
                'investisseur','investor' => \App\Models\Investisseur::class,
                'projet','project'        => \App\Models\Project::class,
                default                   => throw new \Exception("Type d'entité non supporté: $entityType"),
            };
    
            $entity = $entityModel::findOrFail($entityId);
            $stage  = $stageModel::findOrFail($stageId);
    
            // Flag : est-ce que c'est le stage courant de l'entité ?
            $isCurrentStage = ($entity->pipeline_stage_id == $stageId);
    
            // Récupérer les tâches liées à cette étape pour cette entité
            $tasks = \App\Models\Task::where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->where('pipeline_stage_id', $stageId)
                ->with(['user:id,name', 'assignee:id,name'])
                ->orderBy('created_at', 'desc')
                ->get();
    
            // Récupérer les blocages
            $blockages = \App\Models\Blockage::where('blockable_type', $entityModel)
                ->where('blockable_id', $entityId)
                ->where('pipeline_stageable_type', $stageModel)
                ->where('pipeline_stageable_id', $stageId)
                ->with(['assignedUser:id,name','createdByUser:id,name','resolvedBy:id,name'])
                ->orderBy('created_at', 'desc')
                ->get();
    
            // Navigation (prev / next stage)
            $previousStage = $stageModel::where('order', '<', $stage->order)
                ->orderBy('order', 'desc')
                ->first();
    
            $nextStage = $stageModel::where('order', '>', $stage->order)
                ->orderBy('order', 'asc')
                ->first();
    
            // Stats
            $totalTasks      = $tasks->count();
            $completedTasks  = $tasks->where('status', 'completed')->count();
            $taskProgress    = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;
    
            $totalBlockages  = $blockages->count();
            $resolvedBlockages = $blockages->where('status', 'resolved')->count();
            $activeBlockages = $blockages->whereIn('status', ['open','in_progress','actif'])->count();
    
            return response()->json([
                'success' => true,
                'data' => [
                    'entity' => [
                        'id' => $entity->id,
                        'type' => $entityType,
                        'name' => $entity->nom ?? $entity->name ?? $entity->title,
                        'current_stage_id' => $entity->pipeline_stage_id,
                    ],
                    'stage' => [
                        'id' => $stage->id,
                        'name' => $stage->name,
                        'description' => $stage->description ?? '',
                        'order' => $stage->order,
                        'color' => $stage->color ?? '#1890ff',
                        'is_final' => $stage->is_final ?? false,
                        'is_current_stage' => $isCurrentStage, // ✅ nouveau flag
                    ],
                    'navigation' => [
                        'previous_stage' => $previousStage ? [
                            'id' => $previousStage->id,
                            'name' => $previousStage->name,
                        ] : null,
                        'next_stage' => $nextStage ? [
                            'id' => $nextStage->id,
                            'name' => $nextStage->name,
                        ] : null,
                    ],
                    'tasks' => $tasks,
                    'blockages' => $blockages->map(fn($b) => [
                        'id' => $b->id,
                        'name' => $b->name,
                        'description' => $b->description,
                        'blockage_type' => $b->blockage_type,
                        'status' => $b->status,
                        'priority' => $b->priority,
                        'is_blocking' => $b->is_blocking,
                        'is_escalated' => $b->is_escalated,
                        'created_at' => $b->created_at,
                        'resolved_at' => $b->resolved_at,
                        'escalated_at' => $b->escalated_at,
                        'assigned_user' => $b->assignedUser,
                        'created_by_user' => $b->createdByUser,
                        'resolved_by_user' => $b->resolvedBy,
                        'is_overdue' => $b->isOverdue(),
                    ]),
                    'statistics' => [
                        'tasks' => [
                            'total' => $totalTasks,
                            'completed' => $completedTasks,
                            'pending' => $totalTasks - $completedTasks,
                            'progress_percentage' => $taskProgress,
                        ],
                        'blockages' => [
                            'total' => $totalBlockages,
                            'active' => $activeBlockages,
                            'resolved' => $resolvedBlockages,
                            'escalated' => $blockages->where('is_escalated', true)->count(),
                            'overdue' => $blockages->filter(fn($b) => $b->isOverdue())->count(),
                            'resolution_rate' => $totalBlockages > 0 
                                ? round(($resolvedBlockages / $totalBlockages) * 100, 1) 
                                : 0,
                        ],
                        'stage_health' => [
                            'has_active_blockages' => $activeBlockages > 0,
                            'has_critical_blockages' => $blockages->where('priority','critical')->where('status','!=','resolved')->count() > 0,
                            'completion_blocked' => $activeBlockages > 0 && $taskProgress < 100,
                            'ready_to_advance' => $activeBlockages == 0 && $taskProgress == 100,
                        ]
                    ]
                ]
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur lors de la récupération des détails de l'étape: " . $e->getMessage()
            ], 500);
        }
    }
    


    /**
     * Mettre à jour le flag is_final pour toutes les étapes
     * 
     * @param string $model Classe du modèle
     */
    protected function fixFinalStage($model)
    {
        $stages = $model::orderBy('order')->get();
        
        // Réinitialiser tous les is_final à false
        $model::query()->update(['is_final' => false]);
        
        // Définir la dernière étape comme finale
        if ($stages->count() > 0) {
            $lastStage = $stages->last();
            $lastStage->update(['is_final' => true]);
        }
    }


    
}