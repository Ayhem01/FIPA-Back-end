<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SuivieProjet\ProjectExceptionHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectRequest;
use App\Models\Project;
use App\Models\ProjectPipelineStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Investisseur;
use App\Services\PipelineTaskService; // Ensure this is the correct namespace for PipelineTaskService
use Illuminate\Support\Facades\Log;
use App\Services\BlockchainTxLogger; // Ensure this is the correct namespace for BlockchainTxLogger
use App\Services\BlockchainService; // Ensure this is the correct namespace for BlockchainService
use Illuminate\Support\Facades\DB;



class ProjectController extends Controller
{
    /**
     * Afficher la liste des projets
     */
    public function index(Request $request)
    {
        try {
            $query = Project::with(['secteur', 'responsable', 'investisseur']);
            
            // Filtre par secteur
            if ($request->has('secteur_id')) {
                $query->where('secteur_id', $request->secteur_id);
            }
            
            // Filtre par statut principal
            if ($request->has('status')) {
                $status = $request->status;
                if ($status === 'idea') {
                    $query->where('idea', true);
                } elseif ($status === 'in_progress') {
                    $query->where('in_progress', true);
                } elseif ($status === 'in_production') {
                    $query->where('in_production', true);
                } else {
                    $query->where('status', $status);
                }
            }
            
            // Filtre par gouvernorat
            // if ($request->has('governorate_id')) {
            //     $query->where('governorate_id', $request->governorate_id);
            // }
            
            // Filtre par type de pipeline
            if ($request->has('pipeline_type_id')) {
                $query->where('pipeline_type_id', $request->pipeline_type_id);
            }
            
            // Filtre par étape de pipeline
            if ($request->has('pipeline_stage_id')) {
                $query->where('pipeline_stage_id', $request->pipeline_stage_id);
            }
            
            // Filtre par responsable
            if ($request->has('responsable_id')) {
                $query->where('responsable_id', $request->responsable_id);
            }
            
            // Filtre par investisseur
            if ($request->has('investisseur_id')) {
                $query->where('investisseur_id', $request->investisseur_id);
            }
            
            // Filtre par montant d'investissement
            if ($request->has('investment_min')) {
                $query->where('investment_amount', '>=', $request->investment_min);
            }
            
            if ($request->has('investment_max')) {
                $query->where('investment_amount', '<=', $request->investment_max);
            }
            
            // Recherche par nom d'entreprise ou titre
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('company_name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }
            
            // Inclure les relations
            if ($request->has('with')) {
                $relations = explode(',', $request->with);
                $allowedRelations = [
                    'secteur', 'responsable', 'pipelineStage', 
                    'investisseur', 'creator', 'pipelineProgressions.stage'
                ];
                $validRelations = array_intersect($relations, $allowedRelations);
                if (!empty($validRelations)) {
                    $query->with($validRelations);
                }
            }
            
            // Tri
            if ($request->has('sort_by')) {
                $sortField = $request->sort_by;
                $sortDirection = $request->input('sort_direction', 'asc');
                $query->orderBy($sortField, $sortDirection);
            } else {
                $query->latest();
            }
            
            // Pagination
            $perPage = $request->input('per_page', 15);
            $projects = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $projects,
                'meta' => [
                    'total' => $projects->total(),
                    'current_page' => $projects->currentPage(),
                    'per_page' => $projects->perPage(),
                    'last_page' => $projects->lastPage()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des projets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un nouveau projet
     */
    public function store(ProjectRequest $request)
    {
        try {
            $data = $request->validated();
            $data['created_by'] = Auth::id();
            
            // Si le responsable n'est pas spécifié, utiliser l'utilisateur connecté
            if (!isset($data['responsable_id'])) {
                $data['responsable_id'] = Auth::id();
            }
            
            $project = Project::create($data);
            
            // Initialiser le pipeline si un type par défaut existe
            if (!$project->pipeline_type_id) {
                $project->initializePipeline(Auth::id());
            }
            
            // Charger les relations
            $project->load(['secteur', 'responsable', 'pipelineStage']);
            
            return response()->json([
                'success' => true,
                'message' => 'Projet créé avec succès',
                'data' => $project
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du projet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un projet spécifique
     */
    public function show($id)
    {
        try {
            $project = Project::with([
                'secteur', 
                'responsable', 
                'creator',
                'investisseur.prospect.invite',
                'pipelineStage',
                'pipelineProgressions.stage',
                'pipelineProgressions.assignedTo'
            ])->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $project
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Projet non trouvé',
                'error' => $e->getMessage()
            ], 404);
        }
    }

public function update(Request $request, $id)
{
    try {
        $project = Project::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'company_name' => 'sometimes|required|string|max:255',
            'market_target' => 'nullable|string',
            'investment_amount' => 'nullable|numeric|min:0',
            'jobs_expected' => 'nullable|integer|min:0',
            'industrial_zone' => 'nullable|string|max:255',
            'secteur_id' => 'sometimes|required|exists:secteurs,id',
            'responsable_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'nullable|in:planned,in_progress,completed,abandoned,suspended,on_hold',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $validator->errors()
            ], 422);
        }

        // ========================================
        // 1️⃣ BLOCKCHAIN: MISE À JOUR DU PROJET
        // ========================================
        $companyName = $request->input('company_name') ?? $project->company_name;
        $marketTarget = $request->input('market_target') ?? $project->market_target ?? '';
        $investmentAmount = (int)($request->input('investment_amount') ?? $project->investment_amount);
        $jobsExpected = (int)($request->input('jobs_expected') ?? $project->jobs_expected);
        $industrialZone = $request->input('industrial_zone') ?? $project->industrial_zone ?? '';
        $statut = $request->input('status') ?? $project->status ?? 'planned';

        $tx = BlockchainTxLogger::start('update_projet', 'project', $project->id, [
            'projetId' => (int)$project->id,
            'company_name' => $companyName,
            'market_target' => $marketTarget,
            'investment_amount' => $investmentAmount,
            'jobs_expected' => $jobsExpected,
            'industrial_zone' => $industrialZone,
            'statut' => $statut
        ]);

        $txHash = null;
        $blockNumber = null;

        try {
            $service = app(BlockchainService::class);
            
            // ✅ Appel MS: PUT /api/projet/:projetId
            $res = $service->updateProjet(
                projetId: (int)$project->id,
                companyName: $companyName,
                marketTarget: $marketTarget,
                investmentAmount: $investmentAmount,
                jobsExpected: $jobsExpected,
                industrialZone: $industrialZone,
                statut: ucfirst($statut) // Planned, InProgress, etc.
            );
            
            BlockchainTxLogger::success($tx, $res);
            
            $txHash = $res['data']['transactionHash'] ?? null;
            $blockNumber = $res['data']['blockNumber'] ?? null;
            
            \Log::info('Mise à jour projet blockchain réussie', [
                'project_id' => $project->id,
                'tx_hash' => $txHash
            ]);
            
        } catch (\Throwable $e) {
            BlockchainTxLogger::fail($tx, $e->getMessage());
            \Log::warning('Blockchain update failed, continuing with DB update', [
                'project_id' => $project->id,
                'error' => $e->getMessage()
            ]);
        }

        // ========================================
        // 2️⃣ BASE DE DONNÉES: MISE À JOUR
        // ========================================
        $updateData = $request->all();
        
        // Ajouter les infos blockchain si disponibles
        if ($txHash) {
            $updateData['tx_hash'] = $txHash;
            $updateData['tx_block_number'] = $blockNumber;
        }
        
        $project->update($updateData);

        // ========================================
        // 3️⃣ RÉPONSE
        // ========================================
        $project->refresh();
        $project->load(['secteur', 'responsable', 'pipelineStage']);
        
        return response()->json([
            'success' => true,
            'message' => 'Projet mis à jour avec succès',
            'data' => [
                'project' => $project,
                'blockchain_info' => [
                    'tx_hash' => $txHash,
                    'block_number' => $blockNumber
                ]
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error('Erreur update projet', [
            'project_id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour du projet',
            'error' => $e->getMessage()
        ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
    }
}


    /**
     * Supprimer un projet
     */
    public function destroy($id)
{
    try {
        $project = Project::findOrFail($id);
        
        // Vérifier si le projet peut être supprimé
        if ($project->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer un projet terminé.'
            ], 400);
        }

        // ========================================
        // 1️⃣ BLOCKCHAIN: SUPPRESSION
        // ========================================
        $tx = BlockchainTxLogger::start('delete_projet', 'project', $project->id, [
            'projetId' => (int)$project->id,
            'company_name' => $project->company_name
        ]);

        $txHash = null;
        $blockNumber = null;

        try {
            $service = app(BlockchainService::class);
            
            // ✅ Appel MS: DELETE /api/projet/:projetId
            $res = $service->deleteProjet(
                projetId: (int)$project->id
            );
            
            BlockchainTxLogger::success($tx, $res);
            
            $txHash = $res['data']['transactionHash'] ?? null;
            $blockNumber = $res['data']['blockNumber'] ?? null;
            
            \Log::info('Suppression projet blockchain réussie', [
                'project_id' => $project->id,
                'tx_hash' => $txHash
            ]);
            
        } catch (\Throwable $e) {
            BlockchainTxLogger::fail($tx, $e->getMessage());
            \Log::warning('Blockchain deletion failed, continuing with DB deletion', [
                'project_id' => $project->id,
                'error' => $e->getMessage()
            ]);
        }

        // ========================================
        // 2️⃣ BASE DE DONNÉES: SUPPRESSION
        // ========================================
        DB::beginTransaction();

        try {
            // Supprimer les progressions du pipeline
            $project->pipelineProgressions()->delete();
            
            // Supprimer les blockages associés
            if (method_exists($project, 'blockages')) {
                $project->blockages()->delete();
            }
            
            // Supprimer le projet
            $project->delete();
            
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        // ========================================
        // 3️⃣ RÉPONSE
        // ========================================
        return response()->json([
            'success' => true,
            'message' => 'Projet supprimé avec succès',
            'data' => [
                'project_id' => $id,
                'blockchain_info' => [
                    'tx_hash' => $txHash,
                    'block_number' => $blockNumber
                ]
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error('Erreur destroy projet', [
            'project_id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la suppression du projet',
            'error' => $e->getMessage()
        ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
    }
}


public function changeStatus(Request $request, $id)
{
    try {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:planned,in_progress,completed,abandoned,suspended,on_hold'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $project = Project::findOrFail($id);
        $nouveauStatut = $request->status;

        // ========================================
        // 1️⃣ BLOCKCHAIN: MISE À JOUR DU STATUT
        // ========================================
        $tx = BlockchainTxLogger::start('update_projet_status', 'project', $project->id, [
            'projetId' => (int)$project->id,
            'ancien_statut' => $project->status,
            'nouveau_statut' => $nouveauStatut
        ]);

        $txHash = null;
        $blockNumber = null;
        $statusFromChain = null;

        try {
            $service = app(BlockchainService::class);
            
            // ✅ Appel MS: PUT /api/projet/:projetId/status
            $res = $service->updateProjetStatus(
                projetId: (int)$project->id,
                statut: ucfirst($nouveauStatut) // Planned, InProgress, etc.
            );
            
            BlockchainTxLogger::success($tx, $res);
            
            $statusFromChain = $res['data']['status'] ?? null;
            $txHash = $res['data']['transactionHash'] ?? null;
            $blockNumber = $res['data']['blockNumber'] ?? null;
            
            \Log::info('Mise à jour statut projet blockchain réussie', [
                'project_id' => $project->id,
                'ancien_statut' => $project->status,
                'nouveau_statut' => $nouveauStatut,
                'tx_hash' => $txHash
            ]);
            
        } catch (\Throwable $e) {
            BlockchainTxLogger::fail($tx, $e->getMessage());
            \Log::warning('Blockchain status update failed, continuing with DB update', [
                'project_id' => $project->id,
                'error' => $e->getMessage()
            ]);
        }

        // ========================================
        // 2️⃣ BASE DE DONNÉES: MISE À JOUR
        // ========================================
        // Gérer les statuts booléens (ancienne logique de compatibilité)
        $updateData = ['status' => $nouveauStatut];
        
        if (in_array($nouveauStatut, ['idea', 'in_progress', 'in_production'])) {
            $updateData['idea'] = ($nouveauStatut === 'idea');
            $updateData['in_progress'] = ($nouveauStatut === 'in_progress');
            $updateData['in_production'] = ($nouveauStatut === 'in_production');
        }
        
        // Ajouter les infos blockchain si disponibles
        if ($txHash) {
            $updateData['tx_hash'] = $txHash;
            $updateData['tx_block_number'] = $blockNumber;
        }
        
        $project->update($updateData);

        // ========================================
        // 3️⃣ RÉPONSE
        // ========================================
        $project->refresh();
        
        return response()->json([
            'success' => true,
            'message' => 'Statut du projet mis à jour avec succès',
            'data' => [
                'project' => $project,
                'statut_precedent' => $request->status,
                'statut_actuel' => $project->status,
                'blockchain_info' => [
                    'status_chain' => $statusFromChain,
                    'status_label' => $this->getStatusLabel($project->status),
                    'tx_hash' => $txHash,
                    'block_number' => $blockNumber
                ]
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error('Erreur changeStatus projet', [
            'project_id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour du statut',
            'error' => $e->getMessage()
        ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
    }
}

/**
 * Helper pour obtenir le label du statut
 */
private function getStatusLabel(string $status): string
{
    $labels = [
        'planned' => 'Planifié',
        'in_progress' => 'En cours',
        'completed' => 'Terminé',
        'abandoned' => 'Abandonné',
        'suspended' => 'Suspendu',
        'on_hold' => 'En attente'
    ];
    
    return $labels[$status] ?? $status;
}

    
    /**
     * Mettre à jour l'étape du pipeline
     */
    public function updatePipelineStage(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'pipeline_stage_id' => 'required|exists:project_pipeline_stages,id',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $project = Project::findOrFail($id);
            
            if ($project->setStage($request->pipeline_stage_id, Auth::id(), $request->notes)) {
                $project->load(['pipelineStage', 'pipelineProgressions.stage']);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Étape du pipeline mise à jour avec succès',
                    'data' => $project
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Impossible de mettre à jour l\'étape du pipeline'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'étape',
                'error' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    /**
     * Avancer le projet à l'étape suivante du pipeline
     */
    public function advanceStage(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $project = Project::findOrFail($id);
            
            if ($project->advanceToNextStage(Auth::id(), $request->notes)) {
                $project->load(['pipelineStage', 'pipelineProgressions.stage']);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Progression dans le pipeline réussie',
                    'data' => [
                        'project' => $project,
                        'current_stage' => $project->pipelineStage,
                        'progress_percentage' => $project->progress_percentage
                    ]
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'avancer dans le pipeline. Aucune étape suivante disponible.'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'avancement dans le pipeline',
                'error' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }
        /**
     * Initialiser le pipeline pour un projet
     */
    public function initializePipeline(Request $request, $id)
    {
        try {
            $project = Project::findOrFail($id);
            
            if ($project->initializePipeline(Auth::id())) {
                $project->load(['pipelineStage', 'pipelineProgressions.stage']);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Pipeline initialisé avec succès',
                    'data' => $project
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Impossible d\'initialiser le pipeline'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initialisation du pipeline',
                'error' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    /**
     * Récupérer les détails du pipeline d'un projet
     */
    public function getPipelineStatus($id)
{
    try {
        $project = Project::with([
            'pipelineStage',
            'pipelineProgressions.stage',
            'pipelineProgressions.assignedTo'
        ])->findOrFail($id);
        
        if (!$project->pipeline_stage_id) {
            // Initialiser le pipeline si nécessaire
            $initialized = $project->initializePipeline(Auth::id());
            
            if (!$initialized) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible d\'initialiser le pipeline pour ce projet',
                    'data' => [
                        'project' => $project,
                        'pipeline_initialized' => false
                    ]
                ], 400);
            }
            
            // Recharger le projet avec son pipeline
            $project->refresh();
            $project->load([
                'pipelineStage',
                'pipelineProgressions.stage',
                'pipelineProgressions.assignedTo'
            ]);
        }
        
        $currentStage = $project->pipelineStage;
        
        // Récupérer toutes les étapes actives du pipeline
        $allStages = ProjectPipelineStage::where('is_active', true)
                                      ->orderBy('order')
                                      ->get();
                                      
        // Déterminer les étapes complétées
        $completedStageIds = $project->pipelineProgressions()
                                  ->where('completed', true)
                                  ->pluck('stage_id')
                                  ->toArray();
                                  
        $stages = $allStages->map(function ($stage) use ($completedStageIds, $currentStage) {
            $status = 'upcoming';
            if (in_array($stage->id, $completedStageIds)) {
                $status = 'completed';
            } elseif ($currentStage && $stage->id === $currentStage->id) {
                $status = 'current';
            }
            
            return [
                'id' => $stage->id,
                'name' => $stage->name,
                'description' => $stage->description,
                'order' => $stage->order,
                'color' => $stage->color ?? '#007bff',
                'is_final' => (bool) $stage->is_final,
                'status' => $status
            ];
        });
        
        // Récupérer l'historique des étapes
        $stageHistory = $project->pipelineProgressions()
                             ->with(['stage', 'assignedTo'])
                             ->orderBy('created_at', 'desc')
                             ->get()
                             ->map(function ($progression) {
                                 return [
                                     'id' => $progression->id,
                                     'stage' => $progression->stage ? [
                                         'id' => $progression->stage->id,
                                         'name' => $progression->stage->name
                                     ] : null,
                                     'completed' => $progression->completed,
                                     'completed_at' => $progression->completed_at,
                                     'assigned_to' => $progression->assignedTo ? [
                                         'id' => $progression->assignedTo->id,
                                         'name' => $progression->assignedTo->name
                                     ] : null,
                                     'created_at' => $progression->created_at,
                                     'notes' => $progression->notes
                                 ];
                             });
        
        // Calculer le pourcentage de progression
        $progressionPercentage = $project->progressionPercentage();
        
        return response()->json([
            'success' => true,
            'data' => [
                'project' => $project,
                'current_stage' => $currentStage,
                'stages' => $stages,
                'stage_history' => $stageHistory,
                'progression_percentage' => $progressionPercentage,
                'pipeline_completed' => $project->isPipelineCompleted(),
                'conversion_path' => $project->conversion_path
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération du statut du pipeline',
            'error' => $e->getMessage()
        ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
    }
}

    /**
     * Récupérer les projets par secteur
     */
    public function getBySecteur($secteurId)
    {
        try {
            $projects = Project::with(['responsable', 'pipelineStage', 'investisseur'])
                               ->where('secteur_id', $secteurId)
                               ->get();

            return response()->json([
                'success' => true,
                'data' => $projects
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des projets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

   
/**
 * Créer un projet à partir d'un investisseur
 */
public function createFromInvestisseur(Request $request)
{
    // Rediriger vers la bonne méthode
    return app(InvestisseurController::class)->convertToProject(
        $request,
        $request->input('investisseur_id')
    );
}


/**
 * Récupérer les données d'un investisseur pour pré-remplir le formulaire de projet
 */
public function getInvestisseurDataForProject($investisseurId)
{
    try {
        $investisseur = Investisseur::with([
            'entreprise', 
            'secteur', 
            'pays', 
            'responsable',
            'pipelineProgressions.stage'
        ])->findOrFail($investisseurId);
        
        $pipelineStatus = $investisseur->getPipelineStatusDetails();
        $currentStage = $investisseur->pipelineStage;

        // Check if the investor can be converted
        $canConvert = $pipelineStatus['has_completed_final_stage'] || ($currentStage && $currentStage->is_final);

        if (!$canConvert) {
            return response()->json([
                'success' => false,
                'message' => 'Cet investisseur ne peut pas être converti en projet',
                'can_convert' => false,
                'reason' => 'Pipeline non complété ou déjà converti',
                'debug' => [
                    'pipeline_status' => $pipelineStatus,
                    'is_converted' => $investisseur->statut === 'converti'
                ]
            ], 400);
        }
        
        // Préparer les données suggérées pour le formulaire
        $suggestedData = [
            'investisseur_id' => $investisseur->id,
            'title' => "Projet " . ($investisseur->entreprise->nom ?? 'Sans nom'),
            'company_name' => $investisseur->entreprise->nom ?? null,
            'description' => $investisseur->description ?? "Projet d'investissement de " . $investisseur->nom,
            'secteur_id' => $investisseur->secteur_id,
            'investment_amount' => $investisseur->montant_investissement,
            'responsable_id' => $investisseur->responsable_id,
            'contact_source' => 'Conversion depuis investisseur',
            'initial_contact_person' => $investisseur->nom,
            'first_contact_date' => $investisseur->created_at->format('Y-m-d'),
            'notes' => "Projet créé à partir de l'investisseur #" . $investisseur->id,
            
            // Données de référence
            'secteur_name' => $investisseur->secteur->nom ?? null,
            'responsable_name' => $investisseur->responsable->name ?? null,
            'pays_name' => $investisseur->pays->nom ?? null,
            'entreprise_info' => [
                'nom' => $investisseur->entreprise->nom ?? null,
                'secteur' => $investisseur->entreprise->secteur->nom ?? null,
                'pays' => $investisseur->entreprise->pays->nom ?? null
            ]
        ];
        
        return response()->json([
            'success' => true,
            'can_convert' => true,
            'data' => [
                'investisseur' => $investisseur,
                'suggested_project_data' => $suggestedData,
                'pipeline_status' => $pipelineStatus
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des données de l\'investisseur',
            'error' => $e->getMessage()
        ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
    }
}
    public function stats()
    {
        try {
            // Total de projets
            $total = Project::count();
            
            // Par statut
            $byStatus = Project::selectRaw('status, count(*) as count')
                              ->groupBy('status')
                              ->get()
                              ->pluck('count', 'status')
                              ->toArray();
            
            // Par étape
            $byStage = ProjectPipelineStage::withCount(['progressions as active_count' => function($q) {
                                                $q->where('completed', false);
                                             }])
                                          ->get()
                                          ->pluck('active_count', 'name')
                                          ->toArray();
            
            // Montant total d'investissement
            $totalInvestment = Project::sum('investment_amount');
            
            // Projets terminés ce mois
            $completedThisMonth = Project::where('status', Project::STATUS_COMPLETED)
                                        ->whereMonth('updated_at', now()->month)
                                        ->count();
            
            // Projets en retard
            $delayedProjects = Project::whereNotNull('end_date')
                                     ->where('end_date', '<', now())
                                     ->where('status', '!=', Project::STATUS_COMPLETED)
                                     ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'by_status' => $byStatus,
                    'by_stage' => $byStage,
                    'total_investment' => $totalInvestment,
                    'completed_this_month' => $completedThisMonth,
                    'delayed_projects' => $delayedProjects
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    protected function getEntityType()
{
    return 'projet';
}
public function createPipelineStageTask(Request $request, $entityId, $stageId, PipelineTaskService $service)
{
    try {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start' => 'required|date',
            'end' => 'nullable|date|after_or_equal:start',
            'type' => 'required|in:call,meeting,email_journal,note,todo',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assignee_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Utiliser le service pour créer la tâche - entityType déterminé par le contrôleur
        $entityType = $this->getEntityType(); // 'invite', 'prospect', etc.
        $task = $service->createTaskForStage(
            $entityType,
            $entityId,
            $stageId,
            $request->all()
        );

        return response()->json([
            'success' => true,
            'message' => 'Tâche créée avec succès',
            'data' => $task
        ]);
    } catch (\Exception $e) {
        \Log::error("Erreur création tâche pour {$this->getEntityType()}: " . $e->getMessage(), [
            'entity_id' => $entityId,
            'stage_id' => $stageId
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la création de la tâche',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function getPipelineStageTasks($entityId, $stageId, PipelineTaskService $service)
{
    try {
        $entityType = $this->getEntityType();
        $tasks = $service->getTasksForStage($entityType, $entityId, $stageId);
        
        return response()->json([
            'success' => true,
            'data' => $tasks
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des tâches',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function finalizePipelineProgression($id)
{
    try {
        $project = Project::findOrFail($id);

        if ($project->finalizePipelineProgression()) {
            return response()->json([
                'success' => true,
                'message' => 'Pipeline finalized successfully.'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to finalize the pipeline. Ensure the project is in the final stage.'
        ], 400);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while finalizing the pipeline.',
            'error' => $e->getMessage()
        ], 500);
    }
}

private function canConvertInvestisseurToProject(Investisseur $investisseur): bool
{
    $pipelineStatus = $investisseur->getPipelineStatusDetails();
    $currentStage = $investisseur->pipelineStage;

    // Check if the current stage is the final stage or if the final stage is completed
    return $pipelineStatus['has_completed_final_stage'] || ($currentStage && $currentStage->is_final);
}

public function totalJobs()
{
    try {
        // Calculer la somme des emplois attendus dans tous les projets
        $totalJobs = Project::sum('jobs_expected');

        return response()->json([
            'success' => true,
            'data' => [
                'total_jobs' => $totalJobs
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors du calcul du nombre total des emplois',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function investmentBySector()
{
    try {
        $data = Project::selectRaw('secteur_id, SUM(investment_amount) as total_investment')
            ->groupBy('secteur_id')
            ->with('secteur:id,name') // Charger les noms des secteurs
            ->get()
            ->map(function ($item) {
                return [
                    'secteur' => $item->secteur->name ?? 'Non défini',
                    'investment' => $item->total_investment
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des investissements par secteur',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function projectsByStatus()
{
    try {
        $data = Project::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status,
                    'count' => $item->total
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des projets par statut',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function jobsBySector()
{
    try {
        $data = Project::selectRaw('secteur_id, SUM(jobs_expected) as total_jobs')
            ->groupBy('secteur_id')
            ->with('secteur:id,name') // Charger les noms des secteurs
            ->get()
            ->map(function ($item) {
                return [
                    'sector' => $item->secteur->name ?? 'Non défini',
                    'jobs' => $item->total_jobs
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des emplois par secteur',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function projectsByMonth()
{
    try {
        $data = Project::selectRaw('MONTH(created_at) as month, COUNT(*) as total')
            ->groupBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->month,
                    'projects' => $item->total
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des projets par mois',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function delayedProjects()
{
    try {
        $projects = Project::whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->where('status', '!=', Project::STATUS_COMPLETED)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $projects
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des projets en retard',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function averageProgression()
{
    try {
        $averageProgression = Project::all()->avg(function ($project) {
            return $project->progressionPercentage();
        });

        return response()->json([
            'success' => true,
            'data' => [
                'average_progression' => round($averageProgression, 2)
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération de la progression moyenne',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function averageInvestment()
{
    try {
        $averageInvestment = Project::avg('investment_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'average_investment' => round($averageInvestment, 2)
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération du montant moyen d\'investissement',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function projectsByYear()
{
    try {
        $data = Project::selectRaw('YEAR(created_at) as year, COUNT(*) as total_projects')
            ->groupBy('year')
            ->get()
            ->map(function ($item) {
                return [
                    'year' => $item->year,
                    'projects' => $item->total_projects
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des projets par année',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function projectsByResponsable()
{
    try {
        $data = Project::selectRaw('responsable_id, COUNT(*) as total_projects')
            ->groupBy('responsable_id')
            ->with('responsable:id,name') // Charger les noms des responsables
            ->get()
            ->map(function ($item) {
                return [
                    'responsable' => $item->responsable->name ?? 'Non défini',
                    'projects' => $item->total_projects
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des projets par responsable',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function highInvestmentProjects(Request $request)
{
    try {
        $projects = Project::orderBy('investment_amount', 'desc')
            ->take(3)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $projects
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des projets avec des investissements élevés',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function totalBlockedProjects()
{
    try {
        $totalBlocked = Project::where('is_blocked', true)->count();

        \Log::info('Total blocked projects:', ['count' => $totalBlocked]);

        return response()->json([
            'success' => true,
            'data' => [
                'total_blocked_projects' => $totalBlocked
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error('Error in totalBlockedProjects:', ['error' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors du calcul des projets bloqués',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function totalInProductionProjects()
{
    try {
        $totalInProduction = Project::where('in_production', true)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_in_production_projects' => $totalInProduction
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors du calcul des projets en production',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function totalInProgressProjects()
{
    try {
        $totalInProgress = Project::where('in_progress', true)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_in_progress_projects' => $totalInProgress
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors du calcul des projets en cours',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function totalIdeaProjects()
{
    try {
        $totalIdea = Project::where('idea', true)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_idea_projects' => $totalIdea
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors du calcul des projets en idée',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function hierarchicalProjectsBySector()
{
    try {
        // Récupérer les secteurs avec leurs projets
        $data = Project::with('secteur:id,name') // Charger les noms des secteurs
            ->select('secteur_id', 'id', 'title', 'investment_amount', 'jobs_expected', 'status') // Inclure les colonnes nécessaires
            ->get()
            ->groupBy('secteur_id')
            ->map(function ($projects, $secteurId) {
                $secteurName = $projects->first()->secteur->name ?? 'Non défini';
                return [
                    'name' => $secteurName,
                    'value' => $projects->sum('investment_amount'),
                    'jobs' => $projects->sum('jobs_expected'),
                    'children' => $projects->map(function ($project) {
                        return [
                            'name' => $project->title,
                            'value' => $project->investment_amount,
                            'jobs' => $project->jobs_expected,
                            'status' => $project->status
                        ];
                    })->toArray()
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des projets hiérarchiques par secteur',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function pipelineProgression()
{
    try {
        // Récupérer les étapes du pipeline avec le nombre de projets à chaque étape
        $data = ProjectPipelineStage::withCount('projects') // Utiliser la relation définie
            ->orderBy('order', 'asc')
            ->get()
            ->map(function ($stage) {
                return [
                    'name' => $stage->name,
                    'value' => $stage->projects_count,
                    'order' => $stage->order,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération de la progression des projets dans le pipeline',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function investmentByRegion()
{
    try {
        // Récupérer les régions avec le montant total d'investissement
        $data = Project::selectRaw('region_id, SUM(investment_amount) as total_investment')
            ->groupBy('region_id')
            ->with('region:id,name') // Charger les noms des régions
            ->get()
            ->map(function ($item) {
                return [
                    'gov_name_f' => $item->region->name ?? 'Non défini',
                    'investment' => $item->total_investment
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des investissements par région',
            'error' => $e->getMessage()
        ], 500);
    }
}

}