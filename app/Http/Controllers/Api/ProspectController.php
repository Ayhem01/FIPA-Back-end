<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prospect;
use App\Models\ProspectPipelineStage;
use App\Models\ProspectPipelineType;
use App\Models\Investisseur;
use App\Models\InvestorPipelineStage;
use App\Models\InvestorPipelineProgression;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Services\PipelineTaskService;
use Illuminate\Support\Facades\DB; // ✅ AJOUTER CETTE LIGNE


class ProspectController extends Controller
{
    /**
     * Afficher la liste des prospects
     */
    public function index(Request $request)
    {
        try {
            $query = Prospect::with(['entreprise', 'pays', 'secteur', 'responsable']);

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

            // Tri
            $sortField = $request->input('sort_by', 'created_at');
            $sortDirection = $request->input('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // Pagination
            $perPage = $request->input('per_page', 15);
            $prospects = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $prospects,
                'meta' => [
                    'total' => $prospects->total(),
                    'current_page' => $prospects->currentPage(),
                    'per_page' => $prospects->perPage(),
                    'last_page' => $prospects->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des prospects',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un nouveau prospect
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'entreprise_id' => 'required|exists:entreprises,id',
                'nom' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'telephone' => 'nullable|string|max:20',
                'pays_id' => 'nullable|exists:pays,id',
                'secteur_id' => 'nullable|exists:secteurs,id',
                'statut' => 'nullable|in:nouveau,en_cours,qualifie,non_qualifie,converti,perdu',
                'responsable_id' => 'nullable|exists:users,id',
                'valeur_potentielle' => 'nullable|numeric|min:0',
                'devise' => 'nullable|string|max:3',
                'date_dernier_contact' => 'nullable|date',
                'prochain_contact_prevu' => 'nullable|date',
                'description' => 'nullable|string',
                'notes_internes' => 'nullable|string'
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
            
            $prospect = Prospect::create($data);
            
            // Initialiser le pipeline si un type par défaut existe
            $defaultPipelineType = ProspectPipelineType::where('is_default', true)->first();
            if ($defaultPipelineType) {
                $firstStage = ProspectPipelineStage::where('pipeline_type_id', $defaultPipelineType->id)
                    ->orderBy('order')
                    ->first();
                    
                if ($firstStage) {
                    $prospect->pipelineProgressions()->create([
                        'stage_id' => $firstStage->id,
                        'completed' => false,
                        'assigned_to' => Auth::id()
                    ]);
                }
            }
            
            // Charger les relations importantes
            $prospect->load(['entreprise', 'pays', 'secteur', 'responsable', 'pipelineProgressions.stage']);

            return response()->json([
                'success' => true,
                'message' => 'Prospect créé avec succès',
                'data' => $prospect
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du prospect',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un prospect spécifique
     */
    public function show($id)
    {
        try {
            $prospect = Prospect::with([
                'entreprise', 
                'invite', 
                'pays', 
                'secteur', 
                'responsable', 
                'createur',
                'pipelineProgressions.stage',
                'pipelineProgressions.assignedTo',
                'investisseur'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $prospect
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Prospect non trouvé',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Mettre à jour un prospect existant
     */
    public function update(Request $request, $id)
    {
        try {
            $prospect = Prospect::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'entreprise_id' => 'sometimes|required|exists:entreprises,id',
                'nom' => 'sometimes|required|string|max:255',
                'email' => 'nullable|email|max:255',
                'telephone' => 'nullable|string|max:20',
                'pays_id' => 'nullable|exists:pays,id',
                'secteur_id' => 'nullable|exists:secteurs,id',
                'statut' => 'nullable|in:nouveau,en_cours,qualifie,non_qualifie,converti,perdu',
                'responsable_id' => 'nullable|exists:users,id',
                'valeur_potentielle' => 'nullable|numeric|min:0',
                'devise' => 'nullable|string|max:3',
                'date_dernier_contact' => 'nullable|date',
                'prochain_contact_prevu' => 'nullable|date',
                'description' => 'nullable|string',
                'notes_internes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $prospect->update($request->all());
            
            // Recharger les relations importantes
            $prospect->load(['entreprise', 'pays', 'secteur', 'responsable', 'pipelineProgressions.stage']);

            return response()->json([
                'success' => true,
                'message' => 'Prospect mis à jour avec succès',
                'data' => $prospect
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du prospect',
                'error' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    /**
     * Supprimer un prospect
     */
    public function destroy($id)
    {
        try {
            $prospect = Prospect::findOrFail($id);
            $prospect->delete();

            return response()->json([
                'success' => true,
                'message' => 'Prospect supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du prospect',
                'error' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    /**
     * Avancer le prospect à l'étape suivante du pipeline
     */
    
  
     public function advanceStage(Request $request, $id)
{
    try {
        $prospect = Prospect::with(['pipelineStage', 'pipelineProgressions'])->findOrFail($id);

        $result = $prospect->advanceToNextStage(Auth::id(), $request->input('notes'));

        if ($result === false) {
            return response()->json([
                'success' => false,
                'message' => "Impossible d'avancer dans le pipeline. Aucune étape suivante disponible."
            ], 400);
        }

        // 🔄 Recharger les données mises à jour
        $prospect->refresh();
        $prospect->load(['pipelineStage', 'pipelineProgressions.stage']);
        $currentStage = $prospect->pipelineStage;

        if (!$currentStage) {
            return response()->json([
                'success' => false,
                'message' => "Aucune étape actuelle trouvée après l'avancement"
            ], 400);
        }

        // ✅ Mettre à jour le statut du prospect en fonction de l’étape
        if ($currentStage->order <= 2) {
            $prospect->update(['statut' => 'en_cours']); // Correspond à une valeur valide de l'énumération
        } elseif ($currentStage->is_final) {
            $prospect->update(['statut' => 'qualifie']); // Correspond à une valeur valide de l'énumération
        } else {
            $prospect->update(['statut' => 'non_qualifie']); // Correspond à une valeur valide de l'énumération
        }

        // Récupérer la progression actuelle
        $progression = $prospect->pipelineProgressions()
            ->where('stage_id', $currentStage->id)
            ->first();

        return response()->json([
            'success' => true,
            'message' => "Progression dans le pipeline réussie",
            'data'    => [
                'prospect'              => $prospect,
                'current_stage'         => $currentStage,
                'progression'           => $progression,
                'progression_percentage'=> $prospect->progressionPercentage(),
                'is_pipeline_completed' => $prospect->isPipelineCompleted()
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error("Erreur advanceStage Prospect: " . $e->getMessage(), [
            'prospect_id' => $id,
            'trace'       => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => "Erreur lors de l’avancement dans le pipeline",
            'error'   => $e->getMessage()
        ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
    }
}

     
    

public function convertToInvestor(Request $request, $id)
{
    try {
        // Validation des données
        $validator = Validator::make($request->all(), [
            'nom' => 'required|string|max:255',
            'montant_investissement' => 'nullable|numeric|min:0',
            'devise' => 'nullable|string|max:3',
            'interets_specifiques' => 'nullable|string',
            'criteres_investissement' => 'nullable|string',
            'responsable_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
            'date_engagement' => 'nullable|date',
            'date_signature' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false, 
                'message' => 'Validation échouée', 
                'errors' => $validator->errors()
            ], 422);
        }

        $prospect = Prospect::with(['entreprise', 'secteur', 'pays'])->findOrFail($id);

        // Vérification que le prospect n'est pas déjà converti
        if ($prospect->statut === 'converti' || $prospect->converted_at) {
            return response()->json([
                'success' => false,
                'message' => 'Ce prospect est déjà converti.'
            ], 400);
        }

        if (!$prospect->canConvertToInvestor()) {
            return response()->json([
                'success' => false,
                'message' => 'Le prospect doit être dans l\'étape finale pour être converti en investisseur.'
            ], 400);
        }

        // Transaction pour assurer l'intégrité des données
        DB::beginTransaction();
        
        try {
            // 1. Marquer l'étape finale du prospect comme complétée
            if ($prospect->pipelineStage && $prospect->pipelineStage->is_final) {
                $finalProgression = $prospect->pipelineProgressions()
                    ->where('stage_id', $prospect->pipelineStage->id)
                    ->first();

                if ($finalProgression && !$finalProgression->completed) {
                    $finalProgression->update([
                        'completed' => true,
                        'completed_at' => now(),
                        'notes' => ($finalProgression->notes ?? '') . ' - Complétée automatiquement lors de la conversion'
                    ]);
                }
            }

            // 2. Créer l'investisseur
            $investisseur = Investisseur::create([
                'entreprise_id' => $prospect->entreprise_id,
                'nom' => $request->input('nom'),
                'prospect_id' => $prospect->id,
                'email' => $prospect->email,
                'telephone' => $prospect->telephone,
                'adresse' => $prospect->adresse,
                'pays_id' => $prospect->pays_id,
                'secteur_id' => $prospect->secteur_id,
                'montant_investissement' => $request->input('montant_investissement'),
                'devise' => $request->input('devise', 'EUR'),
                'interets_specifiques' => $request->input('interets_specifiques'),
                'criteres_investissement' => $request->input('criteres_investissement'),
                'statut' => 'actif',
                'date_engagement' => $request->input('date_engagement'),
                'date_signature' => $request->input('date_signature'),
                'responsable_id' => $request->input('responsable_id') ?? $prospect->responsable_id,
                'created_by' => Auth::id(),
                'notes_internes' => $request->input('notes') ?? "Investisseur créé à partir du prospect #{$prospect->id}",
                'date_dernier_contact' => $prospect->date_dernier_contact,
                'prochain_contact_prevu' => $prospect->prochain_contact_prevu
            ]);

            // 3. Initialiser le pipeline (MÉTHODE SIMPLIFIÉE comme pour Invite → Prospect)
            $firstStage = InvestorPipelineStage::where('is_active', true)
                ->orderBy('order')
                ->first();

            if ($firstStage) {
                // Mettre à jour l'étape initiale de l'investisseur
                $investisseur->update(['pipeline_stage_id' => $firstStage->id]);
                
                // Créer la première progression
                InvestorPipelineProgression::create([
                    'investisseur_id' => $investisseur->id,
                    'stage_id' => $firstStage->id,
                    'completed' => false,
                    'assigned_to' => Auth::id(),
                    'notes' => 'Étape initiale créée automatiquement lors de la conversion'
                ]);
            }

            // 4. Marquer le prospect comme converti
            $prospect->update([
                'statut' => 'converti',
                'converted_at' => now(),
                'converted_to_id' => $investisseur->id,
                'pipeline_completed_at' => now(),
                'pipeline_completed_by' => Auth::id()
            ]);

            DB::commit();

            // 5. Charger les relations pour la réponse
            $investisseur->load([
                'entreprise', 
                'prospect', 
                'pays', 
                'secteur', 
                'responsable',
                'pipelineStage',
                'pipelineProgressions.stage'
            ]);

            $prospect->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Prospect converti en investisseur avec succès',
                'data' => [
                    'prospect' => $prospect,
                    'investisseur' => $investisseur,
                    'conversion_info' => [
                        'converted_at' => $prospect->converted_at,
                        'pipeline_initialized' => (bool)$firstStage,
                        'pipeline_completed' => true,
                        'final_stage_completed' => true,
                        'progression_percentage' => $prospect->progressionPercentage(),
                        'initial_stage' => $firstStage ? $firstStage->name : null
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

    } catch (\Exception $e) {
        \Log::error('Erreur convertToInvestor: ' . $e->getMessage(), [
            'prospect_id' => $id,
            'user_id' => Auth::id(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la conversion en investisseur',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function updateStatus(Request $request, $id)
{
    try {
        // Valider les données entrantes
        $validated = $request->validate([
            'statut' => 'required|string|in:nouveau,en_cours,qualifie,non_qualifie,converti,perdu'
        ]);

        // Récupérer le prospect
        $prospect = Prospect::findOrFail($id);

        // Mettre à jour le statut
        $prospect->update([
            'statut' => $validated['statut']
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour avec succès',
            'data' => $prospect
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation échouée',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        \Log::error("Erreur lors de la mise à jour du statut : " . $e->getMessage(), [
            'prospect_id' => $id,
            'exception' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => "Une erreur s'est produite lors de la mise à jour du statut"
        ], 500);
    }
}
     

public function getInvestorDataForConversion($id)
{
    try {
        $prospect = Prospect::with([
            'entreprise', 
            'secteur', 
            'pays', 
            'responsable',
            'pipelineProgressions.stage'
        ])->findOrFail($id);
        
        if (!$prospect->canConvertToInvestor()) {
            return response()->json([
                'success' => false,
                'message' => 'Ce prospect ne peut pas être converti en investisseur',
                'can_convert' => false,
                'reason' => 'Pipeline non complété'
            ], 400);
        }
        
        $suggestedData = [
            'prospect_id' => $prospect->id,
            'nom' => $prospect->nom,
            'email' => $prospect->email,
            'telephone' => $prospect->telephone,
            'adresse' => $prospect->adresse,
            'entreprise_name' => $prospect->entreprise->nom ?? null,
            'secteur_id' => $prospect->secteur_id,
            'secteur_name' => $prospect->secteur->nom ?? null,
            'pays_id' => $prospect->pays_id,
            'pays_name' => $prospect->pays->nom ?? null,
            'responsable_id' => $prospect->responsable_id,
            'responsable_name' => $prospect->responsable->name ?? null,
            'devise' => 'EUR',
            'notes' => "Investisseur créé à partir du prospect #{$prospect->id}"
        ];
        
        return response()->json([
            'success' => true,
            'can_convert' => true,
            'data' => [
                'prospect' => $prospect,
                'suggested_data' => $suggestedData,
                'pipeline_status' => $prospect->getPipelineStatusDetails()
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des données',
            'error' => $e->getMessage()
        ], 404);
    }
}

    /**
     * Récupérer les détails du pipeline d'un prospect
     */
 
    //  public function getPipelineStatus($id)
    //  {
    //      try {
    //          $prospect = Prospect::with([
    //              'pipelineStage', 
    //              'pipelineProgressions.stage'
    //          ])->findOrFail($id);
             
    //          if (!$prospect->pipeline_stage_id) {
    //              return response()->json([
    //                  'success' => false,
    //                  'message' => 'Aucun pipeline initialisé pour ce prospect'
    //              ], 400);
    //          }
             
    //          // Récupérer toutes les étapes du pipeline
    //          $stages = ProspectPipelineStage::where('is_active', true)
    //              ->orderBy('order')
    //              ->get();
                 
    //          // Récupérer l'historique des progressions
    //          $progressions = $prospect->pipelineProgressions()
    //              ->with(['stage', 'assignedTo'])
    //              ->orderBy('created_at', 'desc')
    //              ->get();
                 
    //          // Mapper les étapes avec leur statut
    //          $stagesWithStatus = $stages->map(function($stage) use ($progressions) {
    //              $progression = $progressions->firstWhere('stage_id', $stage->id);
                 
    //              return [
    //                  'id' => $stage->id,
    //                  'name' => $stage->name,
    //                  'order' => $stage->order,
    //                  'is_final' => $stage->is_final,
    //                  'color' => $stage->color,
    //                  'status' => $progression ? ($progression->completed ? 'completed' : 'in_progress') : 'pending',
    //                  'completed_at' => $progression && $progression->completed ? $progression->completed_at : null,
    //                  'assigned_to' => $progression && $progression->assignedTo ? [
    //                      'id' => $progression->assignedTo->id,
    //                      'name' => $progression->assignedTo->name
    //                  ] : null
    //              ];
    //          });
             
    //          // Déterminer l'étape actuelle
    //          $currentStage = $prospect->pipelineStage;
             
    //          // Compter les étapes complétées
    //          $completedStages = $progressions->where('completed', true)->count();
    //          $totalStages = $stages->count();
             
    //          // Calculer le pourcentage de progression
    //          $progressionPercentage = $totalStages > 0 ? round(($completedStages / $totalStages) * 100) : 0;
             
    //          // Vérifier si on peut convertir
    //          $canConvert = $prospect->canConvertToInvestor();
             
    //          return response()->json([
    //              'success' => true,
    //              'data' => [
    //                  'current_stage' => $currentStage,
    //                  'stages' => $stagesWithStatus,
    //                  'progression_percentage' => $progressionPercentage,
    //                  'completed_stages' => $completedStages,
    //                  'total_stages' => $totalStages,
    //                  'can_convert' => $canConvert
    //              ]
    //          ]);
    //      } catch (\Exception $e) {
    //          return response()->json([
    //              'success' => false,
    //              'message' => 'Erreur lors de la récupération du pipeline',
    //              'error' => $e->getMessage()
    //          ], 500);
    //      }
    //  }
    
    public function getPipelineStatus($id)
    {
        try {
            $prospect = Prospect::with([
                'pipelineStage', 
                'pipelineProgressions.stage',
                'investisseur' // Relation vers investisseur si converti
            ])->findOrFail($id);
            
            $prospect->append('is_converted'); // Ajouter l'attribut calculé
            
            // Obtenir toutes les étapes du pipeline depuis la base de données
            $allStages = ProspectPipelineStage::getAllStagesInOrder();
    
            return response()->json([
                'success' => true,
                'data' => [
                    'prospect' => $prospect,
                    'current_stage' => $prospect->pipelineStage,
                    'all_stages' => $allStages,
                    'progressions' => $prospect->pipelineProgressions,
                    'progression_percentage' => $prospect->progressionPercentage(),
                    'can_convert' => $prospect->canConvertToInvestor(),
                    'pipeline_details' => $prospect->getPipelineStatusDetails()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur lors de la récupération de l'état du pipeline",
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Récupérer les prospects par entreprise
     */
    public function getByEntreprise($entrepriseId)
    {
        try {
            $prospects = Prospect::with(['pays', 'secteur', 'responsable', 'pipelineProgressions.stage'])
                               ->where('entreprise_id', $entrepriseId)
                               ->get();

            return response()->json([
                'success' => true,
                'data' => $prospects
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des prospects',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer des statistiques sur les prospects
     */
    public function stats()
    {
        try {
            // Total de prospects
            $total = Prospect::count();
            
            // Par statut
            $byStatus = Prospect::selectRaw('statut, count(*) as count')
                              ->groupBy('statut')
                              ->get()
                              ->pluck('count', 'statut')
                              ->toArray();
            
            // Conversion récente (30 derniers jours)
            $recentConversions = Prospect::where('statut', 'converti')
                                      ->where('converted_at', '>=', now()->subDays(30))
                                      ->count();
            
            // Valeur potentielle totale
            $potentialValue = Prospect::where('statut', '!=', 'perdu')
                                   ->sum('valeur_potentielle');
            
            // Étape du pipeline
            $byStage = ProspectPipelineStage::withCount(['progressions as count' => function($q) {
                                                $q->where('completed', false);
                                             }])
                                          ->get()
                                          ->pluck('count', 'name')
                                          ->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'by_status' => $byStatus,
                    'recent_conversions' => $recentConversions,
                    'potential_value' => $potentialValue,
                    'by_stage' => $byStage
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
    return 'prospect';
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

}