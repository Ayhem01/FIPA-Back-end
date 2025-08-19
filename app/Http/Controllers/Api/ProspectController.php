<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prospect;
use App\Models\ProspectPipelineStage;
use App\Models\ProspectPipelineType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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
            
            $prospect = Prospect::findOrFail($id);
            $progression = $prospect->advanceToNextStage(Auth::id(), $request->input('notes'));
            
            if (!$progression) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible d\'avancer dans le pipeline. Aucune étape suivante disponible.'
                ], 400);
            }
            
            // Mettre à jour le statut du prospect en fonction de l'étape
            if ($progression->stage->order <= 2) {
                $prospect->update(['statut' => 'nouveau']);
            } elseif ($progression->stage->is_final) {
                $prospect->update(['statut' => 'qualifie']);
            } else {
                $prospect->update(['statut' => 'en_cours']);
            }
            
            // Recharger le prospect avec ses relations
            $prospect->load(['pipelineProgressions.stage', 'pipelineProgressions.assignedTo']);

            return response()->json([
                'success' => true,
                'message' => 'Progression dans le pipeline réussie',
                'data' => [
                    'prospect' => $prospect,
                    'current_stage' => $progression->stage,
                    'progression' => $progression,
                    'percentage' => $prospect->progressionPercentage()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'avancement dans le pipeline',
                'error' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    /**
     * Initialiser le pipeline pour un prospect
     */
    public function initializePipeline(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'pipeline_type_id' => 'sometimes|required|exists:prospect_pipeline_types,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $prospect = Prospect::findOrFail($id);
            
            // Si le prospect a déjà des progressions, on les supprime
            $prospect->pipelineProgressions()->delete();
            
            // Récupérer le type de pipeline
            $pipelineTypeId = $request->input('pipeline_type_id');
            if (!$pipelineTypeId) {
                $pipelineType = ProspectPipelineType::where('is_default', true)->first();
                if (!$pipelineType) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Aucun type de pipeline par défaut trouvé'
                    ], 400);
                }
                $pipelineTypeId = $pipelineType->id;
            }
            
            // Récupérer la première étape du pipeline
            $firstStage = ProspectPipelineStage::where('pipeline_type_id', $pipelineTypeId)
                ->orderBy('order')
                ->first();
                
            if (!$firstStage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune étape trouvée pour ce type de pipeline'
                ], 400);
            }
            
            // Créer la progression
            $progression = $prospect->pipelineProgressions()->create([
                'stage_id' => $firstStage->id,
                'completed' => false,
                'assigned_to' => Auth::id()
            ]);
            
            // Recharger le prospect avec ses relations
            $prospect->load(['pipelineProgressions.stage', 'pipelineProgressions.assignedTo']);

            return response()->json([
                'success' => true,
                'message' => 'Pipeline initialisé avec succès',
                'data' => [
                    'prospect' => $prospect,
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
     * Convertir un prospect en investisseur
     */
    public function convertToInvestor(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nom' => 'nullable|string|max:255',
                'responsable_id' => 'nullable|exists:users,id',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation échouée',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $prospect = Prospect::findOrFail($id);
            
            if (!$prospect->canConvertToInvestor()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce prospect ne peut pas être converti en investisseur. Il doit avoir complété une étape finale du pipeline.'
                ], 400);
            }
            
            $additionalData = [
                'nom' => $request->input('nom'),
                'responsable_id' => $request->input('responsable_id')
            ];
            
            $investisseur = $prospect->convertToInvestor(
                Auth::id(), 
                $additionalData, 
                $request->input('notes')
            );
            
            if (!$investisseur) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de la conversion du prospect en investisseur'
                ], 500);
            }
            
            // Charger les relations de l'investisseur
            $investisseur->load(['entreprise', 'pays', 'secteur', 'responsable']);

            return response()->json([
                'success' => true,
                'message' => 'Prospect converti en investisseur avec succès',
                'data' => [
                    'prospect' => $prospect,
                    'investisseur' => $investisseur
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la conversion en investisseur',
                'error' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    /**
     * Récupérer les détails du pipeline d'un prospect
     */
    public function getPipelineStatus($id)
    {
        try {
            $prospect = Prospect::with([
                'pipelineProgressions.stage', 
                'pipelineProgressions.assignedTo'
            ])->findOrFail($id);
            
            $currentStage = $prospect->currentStage();
            
            if (!$currentStage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce prospect n\'a pas de pipeline initialisé',
                    'data' => $prospect
                ], 404);
            }
            
            // Récupérer les étapes du pipeline
            $pipelineType = $currentStage->pipelineType;
            $allStages = ProspectPipelineStage::where('pipeline_type_id', $pipelineType->id)
                                             ->orderBy('order')
                                             ->get();
                                             
            // Déterminer les étapes complétées, actuelle et futures
            $completedStageIds = $prospect->pipelineProgressions()
                                         ->where('completed', true)
                                         ->pluck('stage_id')
                                         ->toArray();
                                         
            $stages = $allStages->map(function ($stage) use ($completedStageIds, $currentStage) {
                $status = 'upcoming';
                if (in_array($stage->id, $completedStageIds)) {
                    $status = 'completed';
                } elseif ($stage->id === $currentStage->id) {
                    $status = 'current';
                }
                
                return [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'description' => $stage->description,
                    'order' => $stage->order,
                    'color' => $stage->color,
                    'is_final' => $stage->is_final,
                    'status' => $status
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'prospect' => $prospect,
                    'pipeline_type' => $pipelineType,
                    'current_stage' => $currentStage,
                    'stages' => $stages,
                    'progression_percentage' => $prospect->progressionPercentage(),
                    'can_convert_to_investor' => $prospect->canConvertToInvestor()
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
}