<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investisseur;
use App\Models\InvestorPipelineStage;
use App\Models\InvestorPipelineType;
use App\Models\Project;
use App\Models\InvestorPipelineProgression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Services\PipelineTaskService;

class InvestisseurController extends Controller
{
    /**
     * Afficher la liste des investisseurs
     */
    public function index(Request $request)
    {
        try {
            $query = Investisseur::with(['entreprise', 'prospect', 'pays', 'secteur', 'responsable']);

            // Filtres
            if ($request->has('statut')) {
                $query->where('statut', $request->statut);
            }

            if ($request->has('entreprise_id')) {
                $query->where('entreprise_id', $request->entreprise_id);
            }
            
            if ($request->has('responsable_id')) {
                $query->where('responsable_id', $request->responsable_id);
            }
            
            if ($request->has('secteur_id')) {
                $query->where('secteur_id', $request->secteur_id);
            }
            
            if ($request->has('pays_id')) {
                $query->where('pays_id', $request->pays_id);
            }

            // Filtre par montant d'investissement
            if ($request->has('montant_min')) {
                $query->where('montant_investissement', '>=', $request->montant_min);
            }
            
            if ($request->has('montant_max')) {
                $query->where('montant_investissement', '<=', $request->montant_max);
            }

            // Tri
            $sortField = $request->input('sort_by', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // Pagination
            $perPage = $request->input('per_page', 15);
            $investisseurs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $investisseurs,
                'meta' => [
                    'total' => $investisseurs->total(),
                    'current_page' => $investisseurs->currentPage(),
                    'per_page' => $investisseurs->perPage(),
                    'last_page' => $investisseurs->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des investisseurs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un nouvel investisseur
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'entreprise_id' => 'required|exists:entreprises,id',
                'nom' => 'required|string|max:255',
                'prospect_id' => 'nullable|exists:prospects,id',
                'email' => 'nullable|email|max:255',
                'telephone' => 'nullable|string|max:20',
                'adresse' => 'nullable|string',
                'pays_id' => 'nullable|exists:pays,id',
                'secteur_id' => 'nullable|exists:secteurs,id',
                'montant_investissement' => 'nullable|numeric|min:0',
                'devise' => 'nullable|string|max:3',
                'interets_specifiques' => 'nullable|string',
                'criteres_investissement' => 'nullable|string',
                'statut' => 'nullable|in:actif,negociation,engagement,finalisation,investi,suspendu,inactif',
                'date_engagement' => 'nullable|date',
                'date_signature' => 'nullable|date',
                'responsable_id' => 'nullable|exists:users,id',
                'notes_internes' => 'nullable|string',
                'date_dernier_contact' => 'nullable|date',
                'prochain_contact_prevu' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->all();
            $data['created_by'] = Auth::id();
            
            // Si le responsable n'est pas spécifié, utiliser l'utilisateur connecté
            if (!isset($data['responsable_id'])) {
                $data['responsable_id'] = Auth::id();
            }
            
            // Devise par défaut
            if (!isset($data['devise'])) {
                $data['devise'] = 'EUR';
            }
            
            $investisseur = Investisseur::create($data);
            
            // Initialiser le pipeline si un type par défaut existe
            $defaultPipelineType = InvestorPipelineType::where('is_default', true)->first();
            if ($defaultPipelineType) {
                $firstStage = InvestorPipelineStage::where('pipeline_type_id', $defaultPipelineType->id)
                    ->orderBy('order')
                    ->first();
                    
                if ($firstStage) {
                    $investisseur->pipelineProgressions()->create([
                        'stage_id' => $firstStage->id,
                        'completed' => false,
                        'assigned_to' => Auth::id()
                    ]);
                }
            }
            
            // Charger les relations importantes
            $investisseur->load(['entreprise', 'prospect', 'pays', 'secteur', 'responsable', 'pipelineProgressions.stage']);

            return response()->json([
                'success' => true,
                'message' => 'Investisseur créé avec succès',
                'data' => $investisseur
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'investisseur',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un investisseur spécifique
     */
    public function show($id)
    {
        try {
            $investisseur = Investisseur::with([
                'entreprise', 
                'prospect', 
                'pays', 
                'secteur', 
                'responsable', 
                'createur',
                'pipelineProgressions.stage',
                'pipelineProgressions.assignedTo'
                // Supprimé 'projet' car la table projects n'existe pas encore
            ])->findOrFail($id);
    
            return response()->json([
                'success' => true,
                'data' => $investisseur
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Investisseur non trouvé',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Mettre à jour un investisseur existant
     */
    public function update(Request $request, $id)
    {
        try {
            $investisseur = Investisseur::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'entreprise_id' => 'sometimes|required|exists:entreprises,id',
                'nom' => 'sometimes|required|string|max:255',
                'email' => 'nullable|email|max:255',
                'telephone' => 'nullable|string|max:20',
                'adresse' => 'nullable|string',
                'pays_id' => 'nullable|exists:pays,id',
                'secteur_id' => 'nullable|exists:secteurs,id',
                'montant_investissement' => 'nullable|numeric|min:0',
                'devise' => 'nullable|string|max:3',
                'interets_specifiques' => 'nullable|string',
                'criteres_investissement' => 'nullable|string',
                'statut' => 'nullable|in:actif,negociation,engagement,finalisation,investi,suspendu,inactif',
                'date_engagement' => 'nullable|date',
                'date_signature' => 'nullable|date',
                'responsable_id' => 'nullable|exists:users,id',
                'notes_internes' => 'nullable|string',
                'date_dernier_contact' => 'nullable|date',
                'prochain_contact_prevu' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $investisseur->update($request->all());
            
            // Recharger les relations importantes
            $investisseur->load(['entreprise', 'prospect', 'pays', 'secteur', 'responsable', 'pipelineProgressions.stage']);

            return response()->json([
                'success' => true,
                'message' => 'Investisseur mis à jour avec succès',
                'data' => $investisseur
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'investisseur',
                'error' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    /**
     * Supprimer un investisseur
     */
    public function destroy($id)
    {
        try {
            $investisseur = Investisseur::findOrFail($id);
            $investisseur->delete();

            return response()->json([
                'success' => true,
                'message' => 'Investisseur supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'investisseur',
                'error' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

 
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
            
            $investisseur = Investisseur::findOrFail($id);
            $result = $investisseur->advanceToNextStage(Auth::id(), $request->input('notes'));
            
            if ($result === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible d\'avancer dans le pipeline. Aucune étape suivante disponible.'
                ], 400);
            }
            
            // ✅ RÉCUPÉRER L'ÉTAPE ACTUELLE APRÈS L'AVANCEMENT
            $investisseur->refresh();
            $investisseur->load(['pipelineStage', 'pipelineProgressions.stage']);
            $currentStage = $investisseur->pipelineStage;
            
            if (!$currentStage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune étape actuelle trouvée après l\'avancement'
                ], 400);
            }
            
            // ✅ METTRE À JOUR LE STATUT DE L'INVESTISSEUR EN FONCTION DE L'ÉTAPE ACTUELLE
            if ($currentStage->order <= 2) {
                $investisseur->update(['statut' => 'actif']);
            } elseif ($currentStage->is_final) {
                $investisseur->update(['statut' => 'finalisation']);
            } else {
                $investisseur->update(['statut' => 'negociation']);
            }
            
            // Trouver la progression actuelle
            $progression = $investisseur->pipelineProgressions()
                ->where('stage_id', $currentStage->id)
                ->first();
            
            return response()->json([
                'success' => true,
                'message' => 'Progression dans le pipeline réussie',
                'data' => [
                    'investisseur' => $investisseur,
                    'current_stage' => $currentStage,
                    'progression' => $progression,
                    'percentage' => $investisseur->progressionPercentage()
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error("Erreur advanceStage: " . $e->getMessage(), [
                'investisseur_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'avancement dans le pipeline',
                'error' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    /**
     * Initialiser le pipeline pour un investisseur
     */
    public function initializePipeline(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'pipeline_type_id' => 'sometimes|required|exists:investor_pipeline_types,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $investisseur = Investisseur::findOrFail($id);
            
            // Si l'investisseur a déjà des progressions, on les supprime
            $investisseur->pipelineProgressions()->delete();
            
            // Récupérer le type de pipeline
            $pipelineTypeId = $request->input('pipeline_type_id');
            if (!$pipelineTypeId) {
                $pipelineType = InvestorPipelineType::where('is_default', true)->first();
                if (!$pipelineType) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Aucun type de pipeline par défaut trouvé'
                    ], 400);
                }
                $pipelineTypeId = $pipelineType->id;
            }
            
            // Récupérer la première étape du pipeline
            $firstStage = InvestorPipelineStage::where('pipeline_type_id', $pipelineTypeId)
                ->orderBy('order')
                ->first();
                
            if (!$firstStage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune étape trouvée pour ce type de pipeline'
                ], 400);
            }
            
            // Créer la progression
            $progression = $investisseur->pipelineProgressions()->create([
                'stage_id' => $firstStage->id,
                'completed' => false,
                'assigned_to' => Auth::id()
            ]);
            
            // Recharger l'investisseur avec ses relations
            $investisseur->load(['pipelineProgressions.stage', 'pipelineProgressions.assignedTo']);

            return response()->json([
                'success' => true,
                'message' => 'Pipeline initialisé avec succès',
                'data' => [
                    'investisseur' => $investisseur,
                    'current_stage' => $firstStage,
                    'progression' => $progression
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initialisation du pipeline',
                'error' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    /**
     * Convertir un investisseur en projet
     */
    public function convertToProject(Request $request, $id)
    {
        try {
            // Validation des données
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'responsable_id' => 'nullable|exists:users,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            $investisseur = Investisseur::with(['entreprise', 'secteur', 'pays', 'pipelineStage', 'pipelineProgressions'])->findOrFail($id);

            // Vérification que l'investisseur n'est pas déjà converti
            if ($investisseur->statut === 'converti' || $investisseur->converted_to_project_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cet investisseur est déjà converti en projet.'
                ], 400);
            }

            $currentStage = $investisseur->pipelineStage;

            // Vérification que l'étape actuelle est la finale
            if (!$currentStage || !$currentStage->is_final) {
                return response()->json([
                    'success' => false,
                    'message' => 'L\'investisseur doit être dans l\'étape finale pour être converti en projet.'
                ], 400);
            }

            // Transaction pour assurer l'intégrité des données
            \DB::beginTransaction();

            try {
                // Marquer l'étape finale comme complétée
                $finalProgression = $investisseur->pipelineProgressions()
                    ->where('stage_id', $currentStage->id)
                    ->where('completed', false)
                    ->first();

                if ($finalProgression) {
                    $finalProgression->update([
                        'completed' => true,
                        'completed_at' => now()
                    ]);
                }

                // Marquer tout le pipeline comme complété
                $investisseur->update([
                    'pipeline_completed_at' => now(),
                    'pipeline_completed_by' => Auth::id()
                ]);

                // Créer le projet
                $project = Project::create([
                    'investisseur_id' => $investisseur->id,
                    'title' => $request->input('title'),
                    'description' => $request->input('description'),
                    'company_name' => $investisseur->entreprise->nom ?? null,
                    'secteur_id' => $investisseur->secteur_id,
                    'responsable_id' => $request->input('responsable_id') ?? $investisseur->responsable_id,
                    'created_by' => Auth::id(),
                    'start_date' => $request->input('start_date'),
                    'end_date' => $request->input('end_date'),
                    'notes' => $request->input('notes')
                ]);

                // Initialiser le pipeline du projet
                $project->initializePipeline(Auth::id());

                // Marquer l'investisseur comme converti
                $investisseur->update([
                    'statut' => 'converti',
                    'converted_to_project_at' => now()
                ]);

                \DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Investisseur converti en projet avec succès.',
                    'data' => $project
                ], 201);
            } catch (\Exception $e) {
                \DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            \Log::error('Erreur convertToProject: ' . $e->getMessage(), [
                'investisseur_id' => $id,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la conversion en projet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les détails du pipeline d'un investisseur
     */
   
public function getPipelineStatus($id)
{
    try {
        $investisseur = Investisseur::with([
            'pipelineStage',
            'pipelineProgressions.stage', 
            'pipelineProgressions.assignedTo',
            'prospect',
            'entreprise',
            'responsable'
        ])->findOrFail($id);
        
        $currentStage = $investisseur->pipelineStage;
        
        // Si aucun pipeline n'est initialisé
        if (!$currentStage) {
            // Initialiser automatiquement si nécessaire
            $firstStage = InvestorPipelineStage::where('is_active', true)
                ->orderBy('order')
                ->first();
                
            if ($firstStage) {
                // Créer la première progression
                InvestorPipelineProgression::create([
                    'investisseur_id' => $investisseur->id,
                    'stage_id' => $firstStage->id,
                    'completed' => false,
                    'assigned_to' => $investisseur->responsable_id ?? Auth::id()
                ]);
                
                // Mettre à jour l'étape
                $investisseur->update(['pipeline_stage_id' => $firstStage->id]);
                $investisseur->refresh();
                $currentStage = $firstStage;
            } else {
                return response()->json([
                    'success' => false,
                    'message' => "Cet investisseur n'a pas de pipeline initialisé",
                    'data' => [
                        'investisseur' => $investisseur,
                        'pipeline_initialized' => false,
                        'stages' => [],
                        'progression_percentage' => 0,
                        'can_convert_to_project' => false
                    ]
                ]);
            }
        }
        
        // Récupérer toutes les étapes actives
        $allStages = InvestorPipelineStage::where('is_active', true)
                                         ->orderBy('order')
                                         ->get();
        
        // Reste de la méthode pour afficher les détails du pipeline...
        // [...]

        return response()->json([
            'success' => true,
            'data' => [
                'investisseur' => $investisseur,
                'pipeline_initialized' => true,
                'current_stage' => $currentStage,
                'stages' => $allStages,
                'progression_percentage' => $investisseur->progressionPercentage(),
                'can_convert_to_project' => $investisseur->canConvertToProject()
            ]
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Erreur getPipelineStatus: ' . $e->getMessage(), [
            'investisseur_id' => $id,
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération du statut du pipeline',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Récupérer les investisseurs par entreprise
     */
    public function getByEntreprise($entrepriseId)
    {
        try {
            $investisseurs = Investisseur::with(['pays', 'secteur', 'responsable', 'pipelineProgressions.stage'])
                               ->where('entreprise_id', $entrepriseId)
                               ->get();

            return response()->json([
                'success' => true,
                'data' => $investisseurs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des investisseurs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer des statistiques sur les investisseurs
     */
    public function stats()
    {
        try {
            // Total d'investisseurs
            $total = Investisseur::count();
            
            // Par statut
            $byStatus = Investisseur::selectRaw('statut, count(*) as count')
                              ->groupBy('statut')
                              ->get()
                              ->pluck('count', 'statut')
                              ->toArray();
            
            // Conversions récentes (30 derniers jours)
            $recentConversions = Investisseur::where('statut', 'investi')
                                      ->where('converted_to_project_at', '>=', now()->subDays(30))
                                      ->count();
            
            // Montant total d'investissement
            $totalInvestment = Investisseur::where('statut', '!=', 'inactif')
                                   ->sum('montant_investissement');
            
            // Montant signé
            $signedInvestment = Investisseur::whereNotNull('date_signature')
                                      ->sum('montant_investissement');
            
            // Par étape du pipeline
            $byStage = InvestorPipelineStage::withCount(['progressions as count' => function($q) {
                                                $q->where('completed', false);
                                             }])
                                          ->get()
                                          ->pluck('count', 'name')
                                          ->toArray();
            
            // Par secteur
            $bySector = Investisseur::join('secteurs', 'investisseurs.secteur_id', '=', 'secteurs.id')
                                  ->selectRaw('secteurs.nom, count(*) as count')
                                  ->groupBy('secteurs.nom')
                                  ->get()
                                  ->pluck('count', 'nom')
                                  ->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'by_status' => $byStatus,
                    'recent_conversions' => $recentConversions,
                    'total_investment' => $totalInvestment,
                    'signed_investment' => $signedInvestment,
                    'by_stage' => $byStage,
                    'by_sector' => $bySector
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

    /**
     * Mettre à jour le statut d'un investisseur
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'statut' => 'required|in:actif,negociation,engagement,finalisation,investi,suspendu,inactif',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }

            $investisseur = Investisseur::findOrFail($id);
            
            $updateData = ['statut' => $request->statut];
            
            // Ajouter des champs spécifiques selon le statut
            if ($request->statut === 'engagement' && !$investisseur->date_engagement) {
                $updateData['date_engagement'] = now();
            }
            
            if ($request->statut === 'investi' && !$investisseur->date_signature) {
                $updateData['date_signature'] = now();
            }
            
            $investisseur->update($updateData);
            
            // Ajouter une note si fournie
            if ($request->has('notes')) {
                $currentNotes = $investisseur->notes_internes ?? '';
                $timestamp = now()->format('d/m/Y H:i');
                $newNote = "[{$timestamp}] Changement de statut vers '{$request->statut}': {$request->notes}";
                
                $updatedNotes = $currentNotes 
                    ? $currentNotes . "\n\n" . $newNote 
                    : $newNote;
                    
                $investisseur->update(['notes_internes' => $updatedNotes]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour avec succès',
                'data' => $investisseur->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut',
                'error' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }
    protected function getEntityType()
{
    return 'investor';
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
public function createPipelineStageTask(Request $request, $entityId, $stageId, PipelineTaskService $service)
{
    try {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start' => 'required|date',
            'end' => 'nullable|date|after_or_equal:start',
            'type' => 'required|in:call,meeting,email_journal,note,todo',
            'priority' => 'nullable|in:low,normal,high,urgent',
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
}