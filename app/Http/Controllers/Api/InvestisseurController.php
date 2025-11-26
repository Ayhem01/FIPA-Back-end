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
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\BlockchainService;
use App\Services\BlockchainTxLogger;
use Illuminate\Support\Facades\Log;

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

        // ========================================
        // 1️⃣ BLOCKCHAIN: CRÉATION
        // ========================================
        $nom = $request->input('nom');
        $prospectId = (int)($request->input('prospect_id') ?? 0);
        $montantInvestissement = (int)($request->input('montant_investissement') ?? 0);
        $interetsSpecifiques = (string)($request->input('interets_specifiques') ?? '');
        $criteresInvestissement = (string)($request->input('criteres_investissement') ?? '');
        $statut = $request->input('statut') ?? 'actif';

        $tx = BlockchainTxLogger::start('create_investisseur', 'investisseur', null, [
            'nom' => $nom,
            'prospect_id' => $prospectId,
            'montant_investissement' => $montantInvestissement,
            'interets_specifiques' => $interetsSpecifiques,
            'criteres_investissement' => $criteresInvestissement,
            'statut' => $statut
        ]);

        $investisseurIdFromChain = null;
        $txHash = null;
        $blockNumber = null;

        try {
            $service = app(BlockchainService::class);
            
            $res = $service->createInvestisseurOnChain(
                nom: $nom,
                prospectId: $prospectId,
                montantInvestissement: $montantInvestissement,
                interetsSpecifiques: $interetsSpecifiques,
                criteresInvestissement: $criteresInvestissement,
                statut: $statut
            );
            
            BlockchainTxLogger::success($tx, $res);
            
            $investisseurIdFromChain = $res['data']['investisseurId'] ?? null;
            $txHash = $res['data']['transactionHash'] ?? null;
            $blockNumber = $res['data']['blockNumber'] ?? null;
            
        } catch (\Throwable $e) {
            BlockchainTxLogger::fail($tx, $e->getMessage());
            \Log::warning('Blockchain creation failed', ['error' => $e->getMessage()]);
        }

        // ========================================
        // 2️⃣ BASE DE DONNÉES: CRÉATION
        // ========================================
        DB::beginTransaction();

        try {
            $data = $request->all();
            $data['created_by'] = Auth::id();
            $data['responsable_id'] = $data['responsable_id'] ?? Auth::id();
            $data['devise'] = $data['devise'] ?? 'EUR';
            $data['tx_hash'] = $txHash;
            $data['tx_block_number'] = $blockNumber;
            
            $investisseur = Investisseur::create($data);
            
            // Mettre à jour la TX avec l'ID local
            if ($tx) {
                $tx->update(['related_id' => $investisseur->id]);
            }
            
            // Initialiser le pipeline
            $defaultPipelineType = InvestorPipelineType::where('is_default', true)->first();
            if ($defaultPipelineType) {
                $firstStage = InvestorPipelineStage::where('pipeline_type_id', $defaultPipelineType->id)
                    ->orderBy('order')
                    ->first();
                    
                if ($firstStage) {
                    $investisseur->update(['pipeline_stage_id' => $firstStage->id]);
                    
                    $investisseur->pipelineProgressions()->create([
                        'stage_id' => $firstStage->id,
                        'completed' => false,
                        'assigned_to' => Auth::id()
                    ]);
                }
            }
            
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        // ========================================
        // 3️⃣ RÉPONSE
        // ========================================
        $investisseur->load(['entreprise', 'prospect', 'pays', 'secteur', 'responsable', 'pipelineProgressions.stage']);

        return response()->json([
            'success' => true,
            'message' => 'Investisseur créé avec succès',
            'data' => [
                'investisseur' => $investisseur,
                'blockchain_info' => [
                    'investisseur_id' => $investisseurIdFromChain,
                    'tx_hash' => $txHash,
                    'block_number' => $blockNumber
                ]
            ]
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
            'statut' => 'nullable|in:actif,negociation,engagement,finalisation,investi,suspendu,inactif,converti',
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

        // ========================================
        // 1️⃣ BLOCKCHAIN: MISE À JOUR
        // ========================================
        $nom = $request->input('nom') ?? $investisseur->nom;
        $montantInvestissement = (int)($request->input('montant_investissement') ?? $investisseur->montant_investissement);
        $interetsSpecifiques = (string)($request->input('interets_specifiques') ?? $investisseur->interets_specifiques ?? '');
        $criteresInvestissement = (string)($request->input('criteres_investissement') ?? $investisseur->criteres_investissement ?? '');
        $statut = $request->input('statut') ?? $investisseur->statut ?? 'actif';

        $tx = BlockchainTxLogger::start('update_investisseur', 'investisseur', $investisseur->id, [
            'investisseurId' => (int)$investisseur->id,
            'nom' => $nom,
            'montant_investissement' => $montantInvestissement,
            'interets_specifiques' => $interetsSpecifiques,
            'criteres_investissement' => $criteresInvestissement,
            'statut' => $statut
        ]);

        $txHash = null;
        $blockNumber = null;

        try {
            $service = app(BlockchainService::class);
            
            // ✅ Appel MS: PUT /api/investisseur/:investisseurId
            $res = $service->updateInvestisseur(
                investisseurId: (int)$investisseur->id,
                nom: $nom,
                montantInvestissement: $montantInvestissement,
                interetsSpecifiques: $interetsSpecifiques,
                criteresInvestissement: $criteresInvestissement,
                statut: $statut
            );
            
            BlockchainTxLogger::success($tx, $res);
            
            $txHash = $res['data']['transactionHash'] ?? null;
            $blockNumber = $res['data']['blockNumber'] ?? null;
            
            \Log::info('Mise à jour investisseur blockchain réussie', [
                'investisseur_id' => $investisseur->id,
                'tx_hash' => $txHash
            ]);
            
        } catch (\Throwable $e) {
            BlockchainTxLogger::fail($tx, $e->getMessage());
            \Log::warning('Blockchain update failed, continuing with DB update', [
                'investisseur_id' => $investisseur->id,
                'error' => $e->getMessage()
            ]);
            // Ne pas bloquer la mise à jour locale
        }

        // ========================================
        // 2️⃣ BASE DE DONNÉES: MISE À JOUR
        // ========================================
        DB::beginTransaction();

        try {
            $updateData = $request->all();
            
            // Ajouter les infos blockchain si disponibles
            if ($txHash) {
                $updateData['tx_hash'] = $txHash;
                $updateData['tx_block_number'] = $blockNumber;
            }
            
            $investisseur->update($updateData);
            
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        // ========================================
        // 3️⃣ RÉPONSE
        // ========================================
        $investisseur->refresh();
        $investisseur->load(['entreprise', 'prospect', 'pays', 'secteur', 'responsable', 'pipelineStage', 'pipelineProgressions.stage']);

        return response()->json([
            'success' => true,
            'message' => 'Investisseur mis à jour avec succès',
            'data' => [
                'investisseur' => $investisseur,
                'blockchain_info' => [
                    'tx_hash' => $txHash,
                    'block_number' => $blockNumber
                ]
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error('Erreur update investisseur', [
            'investisseur_id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour de l\'investisseur',
            'error' => $e->getMessage()
        ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
    }
}

public function updateStatus(Request $request, $id)
{
    try {
        $validator = Validator::make($request->all(), [
            'statut' => 'required|in:actif,negociation,engagement,finalisation,investi,suspendu,inactif'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $validator->errors()
            ], 422);
        }

        $investisseur = Investisseur::findOrFail($id);
        $nouveauStatut = $request->input('statut');

        // ========================================
        // 1️⃣ BLOCKCHAIN: MISE À JOUR DU STATUT
        // ========================================
        $tx = BlockchainTxLogger::start('update_investisseur_status', 'investisseur', $investisseur->id, [
            'investisseurId' => (int)$investisseur->id,
            'ancien_statut' => $investisseur->statut,
            'nouveau_statut' => $nouveauStatut
        ]);

        $txHash = null;
        $blockNumber = null;
        $statusFromChain = null;

        try {
            $service = app(BlockchainService::class);
            
            // ✅ Appel MS: PUT /api/investisseur/:investisseurId/status
            $res = $service->updateInvestisseurStatus(
                investisseurId: (int)$investisseur->id,
                statut: $nouveauStatut
            );
            
            BlockchainTxLogger::success($tx, $res);
            
            $statusFromChain = $res['data']['status'] ?? null;
            $txHash = $res['data']['transactionHash'] ?? null;
            $blockNumber = $res['data']['blockNumber'] ?? null;
            
            \Log::info('Mise à jour statut investisseur blockchain réussie', [
                'investisseur_id' => $investisseur->id,
                'ancien_statut' => $investisseur->statut,
                'nouveau_statut' => $nouveauStatut,
                'status_chain' => $statusFromChain,
                'tx_hash' => $txHash
            ]);
            
        } catch (\Throwable $e) {
            BlockchainTxLogger::fail($tx, $e->getMessage());
            \Log::warning('Blockchain status update failed, continuing with DB update', [
                'investisseur_id' => $investisseur->id,
                'error' => $e->getMessage()
            ]);
            // Ne pas bloquer la mise à jour locale
        }

        // ========================================
        // 2️⃣ BASE DE DONNÉES: MISE À JOUR
        // ========================================
        DB::beginTransaction();

        try {
            $updateData = [
                'statut' => $nouveauStatut
            ];
            
            // Ajouter les infos blockchain si disponibles
            if ($txHash) {
                $updateData['tx_hash'] = $txHash;
                $updateData['tx_block_number'] = $blockNumber;
            }
            
            // ✅ Actions automatiques selon le statut
            switch ($nouveauStatut) {
                case 'investi':
                    // Marquer comme converti si pas déjà fait
                    if (!$investisseur->converted_to_project_at) {
                        $updateData['date_signature'] = $updateData['date_signature'] ?? now();
                    }
                    break;
                    
                case 'finalisation':
                    // Mettre à jour la date d'engagement si pas déjà définie
                    if (!$investisseur->date_engagement) {
                        $updateData['date_engagement'] = now();
                    }
                    break;
                    
                case 'inactif':
                case 'suspendu':
                    // Pas d'action spécifique pour l'instant
                    break;
            }
            
            $investisseur->update($updateData);
            
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        // ========================================
        // 3️⃣ RÉPONSE
        // ========================================
        $investisseur->refresh();
        $investisseur->load(['entreprise', 'prospect', 'pays', 'secteur', 'responsable', 'pipelineStage']);

        return response()->json([
            'success' => true,
            'message' => 'Statut de l\'investisseur mis à jour avec succès',
            'data' => [
                'investisseur' => $investisseur,
                'statut_precedent' => $request->input('statut'),
                'statut_actuel' => $investisseur->statut,
                'blockchain_info' => [
                    'status_chain' => $statusFromChain,
                    'status_label' => $this->getStatusLabel($investisseur->statut),
                    'tx_hash' => $txHash,
                    'block_number' => $blockNumber
                ]
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error('Erreur update status investisseur', [
            'investisseur_id' => $id,
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

public function destroy($id)
{
    try {
        $investisseur = Investisseur::findOrFail($id);
        
        // Vérifier si l'investisseur est déjà converti en projet
        if ($investisseur->statut === 'converti' || $investisseur->converted_to_project_at) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer un investisseur déjà converti en projet.'
            ], 400);
        }

        // ========================================
        // 1️⃣ BLOCKCHAIN: SUPPRESSION
        // ========================================
        $tx = BlockchainTxLogger::start('delete_investisseur', 'investisseur', $investisseur->id, [
            'investisseurId' => (int)$investisseur->id,
            'nom' => $investisseur->nom
        ]);

        $txHash = null;
        $blockNumber = null;

        try {
            $service = app(BlockchainService::class);
            
            // ✅ Appel MS: DELETE /api/investisseur/:investisseurId
            $res = $service->deleteInvestisseur(
                investisseurId: (int)$investisseur->id
            );
            
            BlockchainTxLogger::success($tx, $res);
            
            $txHash = $res['data']['transactionHash'] ?? null;
            $blockNumber = $res['data']['blockNumber'] ?? null;
            
            \Log::info('Suppression investisseur blockchain réussie', [
                'investisseur_id' => $investisseur->id,
                'tx_hash' => $txHash
            ]);
            
        } catch (\Throwable $e) {
            BlockchainTxLogger::fail($tx, $e->getMessage());
            \Log::warning('Blockchain deletion failed, continuing with DB deletion', [
                'investisseur_id' => $investisseur->id,
                'error' => $e->getMessage()
            ]);
            // Ne pas bloquer la suppression locale
        }

        // ========================================
        // 2️⃣ BASE DE DONNÉES: SUPPRESSION
        // ========================================
        DB::beginTransaction();

        try {
            // Supprimer les progressions du pipeline
            $investisseur->pipelineProgressions()->delete();
            
            // Supprimer les tâches associées (si table tasks existe)
            if (method_exists($investisseur, 'tasks')) {
                $investisseur->tasks()->delete();
            }
            
            // Supprimer l'investisseur
            $investisseur->delete();
            
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
            'message' => 'Investisseur supprimé avec succès',
            'data' => [
                'investisseur_id' => $id,
                'blockchain_info' => [
                    'tx_hash' => $txHash,
                    'block_number' => $blockNumber
                ]
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error('Erreur destroy investisseur', [
            'investisseur_id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

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


public function convertToProject(Request $request, $id)
{
    try {
        // Validation des données
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'market_target' => 'required|string',
            'investment_amount' => 'required|numeric|min:0',
            'jobs_expected' => 'required|integer|min:0',
            'industrial_zone' => 'required|string|max:255',
            'description' => 'nullable|string',
            'responsable_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:planned,in_progress,completed,abandoned,suspended,on_hold' 
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $validator->errors()
            ], 422);
        }

        $investisseur = Investisseur::with(['entreprise', 'secteur', 'pays', 'pipelineStage', 'pipelineProgressions'])->findOrFail($id);
        $userId = Auth::id();

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

        // --- PRÉPARATION DES DONNÉES ---
        $companyName = $request->input('company_name');
        $marketTarget = $request->input('market_target');
        $investmentAmount = (int)$request->input('investment_amount');
        $jobsExpected = (int)$request->input('jobs_expected');
        $industrialZone = $request->input('industrial_zone');
        $statut = $request->input('status') ?? 'planned';  


        // ========================================
        // 1️⃣ BLOCKCHAIN: CONVERTIR L'INVESTISSEUR EN PROJET
        // ========================================
       $tx1 = BlockchainTxLogger::start('convert_investisseur_to_projet', 'investisseur', $investisseur->id, [
            'investisseurId' => (int)$investisseur->id,
            'company_name' => $companyName,
            'market_target' => $marketTarget,
            'investment_amount' => $investmentAmount,
            'jobs_expected' => $jobsExpected,
            'industrial_zone' => $industrialZone,
            'statut' => $statut
        ]);

        $projetIdFromConversion = null;
        $conversionTxHash = null;
        $conversionBlockNumber = null;

         try {
            $service = app(BlockchainService::class);
            
            // ✅ Le service va mapper automatiquement 'planned' → 'Planned'
            $resConvert = $service->convertInvestisseurToProjet(
                investisseurId: (int)$investisseur->id,
                companyName: $companyName,
                marketTarget: $marketTarget,
                investmentAmount: $investmentAmount,
                jobsExpected: $jobsExpected,
                industrialZone: $industrialZone,
                statut: $statut  // ✅ Passer le statut Laravel directement
            );
            
            BlockchainTxLogger::success($tx1, $resConvert);
            
            $projetIdFromConversion = $resConvert['data']['projetId'] ?? null;
            $conversionTxHash = $resConvert['data']['transactionHash'] ?? null;
            $conversionBlockNumber = $resConvert['data']['blockNumber'] ?? null;
            
            \Log::info('Conversion investisseur → projet réussie', [
                'investisseur_id' => $investisseur->id,
                'projet_id_chain' => $projetIdFromConversion,
                'tx_hash' => $conversionTxHash
            ]);
            
        } catch (\Throwable $e) {
            BlockchainTxLogger::fail($tx1, $e->getMessage());
            \Log::error('Blockchain conversion failed', [
                'investisseur_id' => $investisseur->id,
                'error' => $e->getMessage()
            ]);
        }

        // ========================================
        // 2️⃣ BLOCKCHAIN: CRÉER LE PROJET
        // ========================================
                $tx2 = BlockchainTxLogger::start('create_projet', 'investisseur', $investisseur->id, [
            'company_name' => $companyName,
            'market_target' => $marketTarget,
            'investment_amount' => $investmentAmount,
            'jobs_expected' => $jobsExpected,
            'industrial_zone' => $industrialZone,
            'investisseur_id' => (int)$investisseur->id,
            'statut' => $statut
        ]);

        $projetIdFromCreation = null;
        $creationTxHash = null;
        $creationBlockNumber = null;

        try {
            $service = app(BlockchainService::class);
            
            // ✅ Le service va mapper automatiquement 'planned' → 'Planned'
            $resCreate = $service->createProjetOnChain(
                companyName: $companyName,
                marketTarget: $marketTarget,
                investmentAmount: $investmentAmount,
                jobsExpected: $jobsExpected,
                industrialZone: $industrialZone,
                investisseurId: (int)$investisseur->id,
                statut: $statut  // ✅ Passer le statut Laravel directement
            );
            
            BlockchainTxLogger::success($tx2, $resCreate);
            
            $projetIdFromCreation = $resCreate['data']['projetId'] ?? null;
            $creationTxHash = $resCreate['data']['transactionHash'] ?? null;
            $creationBlockNumber = $resCreate['data']['blockNumber'] ?? null;
            
            \Log::info('Création projet réussie', [
                'investisseur_id' => $investisseur->id,
                'projet_id_chain' => $projetIdFromCreation,
                'tx_hash' => $creationTxHash
            ]);
            
        } catch (\Throwable $e) {
            BlockchainTxLogger::fail($tx2, $e->getMessage());
            \Log::error('Blockchain creation failed', [
                'investisseur_id' => $investisseur->id,
                'error' => $e->getMessage()
            ]);
        }

        // ========================================
        // 3️⃣ BASE DE DONNÉES: CRÉATION DU PROJET
        // ========================================
        DB::beginTransaction();

        try {
            // 1. Marquer l'étape finale comme complétée
            $finalProgression = $investisseur->pipelineProgressions()
                ->where('stage_id', $currentStage->id)
                ->where('completed', false)
                ->first();

            if ($finalProgression) {
                $finalProgression->update([
                    'completed' => true,
                    'completed_at' => now(),
                    'notes' => ($finalProgression->notes ?? '') . ' - Complétée automatiquement lors de la conversion en projet'
                ]);
            }

            // 2. Marquer tout le pipeline comme complété
            $investisseur->update([
                'pipeline_completed_at' => now(),
                'pipeline_completed_by' => $userId
            ]);

            // 3. Créer le projet
                $project = Project::create([
                'investisseur_id' => $investisseur->id,
                'title' => $request->input('title'),
                'company_name' => $companyName,
                'market_target' => $marketTarget,
                'investment_amount' => $investmentAmount,
                'jobs_expected' => $jobsExpected,
                'industrial_zone' => $industrialZone,
                'description' => $request->input('description'),
                'secteur_id' => $investisseur->secteur_id,
                'responsable_id' => $request->input('responsable_id') ?? $investisseur->responsable_id,
                'created_by' => $userId,
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'notes' => $request->input('notes'),
                'status' => $statut,  
                'tx_hash' => $creationTxHash,
                'tx_block_number' => $creationBlockNumber,
            ]);

            // 4. Initialiser le pipeline du projet
            $projectPipelineInitialized = false;
            if (method_exists($project, 'initializePipeline')) {
                $project->initializePipeline($userId);
                $projectPipelineInitialized = true;
            }

            // 5. Marquer l'investisseur comme converti (TX de conversion)
            $investisseur->update([
                'statut' => 'converti',
                'converted_to_project_at' => now(),
                'project_id' => $project->id,
                'tx_hash' => $conversionTxHash,
                'tx_block_number' => $conversionBlockNumber,
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        // ========================================
        // 4️⃣ RÉPONSE
        // ========================================
        $investisseur->refresh();
        $project->load(['investisseur', 'secteur', 'responsable']);

        return response()->json([
            'success' => true,
            'message' => 'Investisseur converti en projet avec succès',
            'data' => [
                'investisseur' => $investisseur,
                'project' => $project,
                'conversion_info' => [
                    'converted_at' => $investisseur->converted_to_project_at,
                    'pipeline_completed' => true,
                    'final_stage_completed' => true,
                    'project_pipeline_initialized' => $projectPipelineInitialized
                ],
                'blockchain_info' => [
                    'conversion' => [
                        'projet_id' => $projetIdFromConversion,
                        'tx_hash' => $conversionTxHash,
                        'block_number' => $conversionBlockNumber
                    ],
                    'creation' => [
                        'projet_id' => $projetIdFromCreation,
                        'tx_hash' => $creationTxHash,
                        'block_number' => $creationBlockNumber
                    ]
                ]
            ]
        ], 201);
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
 * Retourne le type d'entité pour le service de pipeline
 */
protected function getEntityType(): string
{
    return 'investisseur';
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
        ])->find($id);
        
        if (!$investisseur) {
            return response()->json([
                'success' => false,
                'message' => 'Investisseur introuvable',
                'error' => "Aucun investisseur trouvé avec l'ID {$id}"
            ], 404);
        }

        // Obtenir toutes les étapes du pipeline depuis la base de données
        $allStages = InvestorPipelineStage::where('is_active', true)
                                         ->orderBy('order')
                                         ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'investisseur' => $investisseur,
                'current_stage' => $investisseur->pipelineStage,
                'all_stages' => $allStages,
                'progressions' => $investisseur->pipelineProgressions,
                'progression_percentage' => $investisseur->progressionPercentage(),
                'can_convert' => $investisseur->canConvertToProject(),
                'pipeline_details' => $investisseur->getPipelineStatusDetails()
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error('Erreur getPipelineStatus investisseur', [
            'investisseur_id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération du statut du pipeline',
            'error' => config('app.debug') ? $e->getMessage() : 'Une erreur est survenue'
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

/**
 * Dashboard complet avec toutes les statistiques
 */

public function dashboard()
{
    try {
        // 📊 STATISTIQUES GLOBALES
        $totalInvestisseurs = Investisseur::count();
        $nouveauCeMois = Investisseur::whereMonth('created_at', now()->month)->count();
        $totalInvesti = Investisseur::where('statut', 'investi')->count();
        $montantTotal = Investisseur::sum('montant_investissement');
        $montantSigne = Investisseur::whereNotNull('date_signature')->sum('montant_investissement');
        $tauxConversion = $totalInvestisseurs > 0 ? round(($totalInvesti / $totalInvestisseurs) * 100, 2) : 0;

        // 📈 CHART PAR STATUT
        $statusData = Investisseur::select('statut', DB::raw('COUNT(*) as count'))
                                 ->groupBy('statut')
                                 ->orderByDesc('count')
                                 ->get()
                                 ->map(function ($item) {
                                     return [
                                         'name' => ucfirst($item->statut),
                                         'value' => $item->count,
                                         'code' => $item->statut
                                     ];
                                 });

        // 🔄 CHART PIPELINE
        $pipelineData = InvestorPipelineStage::leftJoin('investor_pipeline_progressions', 'investor_pipeline_stages.id', '=', 'investor_pipeline_progressions.stage_id')
                                            ->leftJoin('investisseurs', 'investor_pipeline_progressions.investisseur_id', '=', 'investisseurs.id')
                                            ->select(
                                                'investor_pipeline_stages.name as stage_name',
                                                'investor_pipeline_stages.order',
                                                DB::raw('COUNT(DISTINCT investisseurs.id) as count')
                                            )
                                            ->where('investor_pipeline_stages.is_active', true)
                                            ->whereNull('investisseurs.deleted_at')
                                            ->groupBy('investor_pipeline_stages.id', 'investor_pipeline_stages.name', 'investor_pipeline_stages.order')
                                            ->orderBy('investor_pipeline_stages.order')
                                            ->get()
                                            ->map(function ($item) {
                                                return [
                                                    'name' => $item->stage_name,
                                                    'value' => $item->count,
                                                    'order' => $item->order
                                                ];
                                            });

        // 📅 CHART ÉVOLUTION MENSUELLE
        $evolutionData = Investisseur::select(
                            DB::raw('YEAR(created_at) as year'),
                            DB::raw('MONTH(created_at) as month'),
                            DB::raw('COUNT(*) as total'),
                            DB::raw('SUM(CASE WHEN statut = "investi" THEN 1 ELSE 0 END) as convertis'),
                            DB::raw('SUM(montant_investissement) as total_investment')
                        )
                        ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
                        ->groupBy('year', 'month')
                        ->orderBy('year')
                        ->orderBy('month')
                        ->get()
                        ->map(function ($item) {
                            $date = Carbon::createFromDate($item->year, $item->month, 1);
                            return [
                                'name' => $date->format('M Y'),
                                'total' => $item->total,
                                'convertis' => $item->convertis,
                                'total_investment' => round($item->total_investment, 2),
                                'conversion_rate' => $item->total > 0 ? round(($item->convertis / $item->total) * 100, 1) : 0
                            ];
                        });

        // 💰 CHART ANALYSE INVESTISSEMENTS
        $investmentData = collect([
            ['min' => 0, 'max' => 50000, 'label' => '0-50K'],
            ['min' => 50000, 'max' => 100000, 'label' => '50K-100K'],
            ['min' => 100000, 'max' => 500000, 'label' => '100K-500K'],
            ['min' => 500000, 'max' => 1000000, 'label' => '500K-1M'],
            ['min' => 1000000, 'max' => null, 'label' => '1M+']
        ])->map(function ($tranche) {
            $query = Investisseur::where('montant_investissement', '>=', $tranche['min']);
            if ($tranche['max']) {
                $query->where('montant_investissement', '<', $tranche['max']);
            }
            
            $count = $query->count();
            $totalValue = $query->sum('montant_investissement');
            $convertis = $query->where('statut', 'investi')->count();
            
            return [
                'name' => $tranche['label'],
                'value' => $count,
                'total_value' => round($totalValue, 2),
                'convertis' => $convertis,
                'conversion_rate' => $count > 0 ? round(($convertis / $count) * 100, 1) : 0
            ];
        });

        // 🏢 CHART PAR SECTEUR
        $sectorData = Investisseur::join('secteurs', 'investisseurs.secteur_id', '=', 'secteurs.id')
                                 ->select(
                                     'secteurs.name as secteur',
                                     DB::raw('COUNT(investisseurs.id) as count'),
                                     DB::raw('SUM(investisseurs.montant_investissement) as total_investment')
                                 )
                                 ->groupBy('secteurs.name')
                                 ->orderByDesc('total_investment')
                                 ->limit(10)
                                 ->get()
                                 ->map(function ($item) {
                                     return [
                                         'name' => $item->secteur,
                                         'value' => $item->count,
                                         'total_investment' => round($item->total_investment, 2)
                                     ];
                                 });

        // 🌍 CHART PAR PAYS
        $countryData = Investisseur::join('pays', 'investisseurs.pays_id', '=', 'pays.id')
                                  ->select(
                                      'pays.name_pays as country',
                                      DB::raw('COUNT(investisseurs.id) as count'),
                                      DB::raw('SUM(investisseurs.montant_investissement) as total_investment')
                                  )
                                  ->groupBy('pays.name_pays')
                                  ->orderByDesc('count')
                                  ->limit(8)
                                  ->get()
                                  ->map(function ($item) {
                                      return [
                                          'name' => $item->country,
                                          'value' => $item->count,
                                          'total_investment' => round($item->total_investment, 2)
                                      ];
                                  });

        // 👤 CHART PAR RESPONSABLE
        $responsableData = Investisseur::join('users', 'investisseurs.responsable_id', '=', 'users.id')
                                      ->select(
                                          'users.name as responsable',
                                          DB::raw('COUNT(investisseurs.id) as total'),
                                          DB::raw('SUM(CASE WHEN investisseurs.statut = "investi" THEN 1 ELSE 0 END) as convertis'),
                                          DB::raw('SUM(investisseurs.montant_investissement) as total_value')
                                      )
                                      ->groupBy('users.name')
                                      ->having('total', '>', 0)
                                      ->get()
                                      ->map(function ($item) {
                                          $conversionRate = $item->total > 0 ? round(($item->convertis / $item->total) * 100, 1) : 0;
                                          return [
                                              'name' => $item->responsable,
                                              'value' => $item->total,
                                              'convertis' => $item->convertis,
                                              'conversion_rate' => $conversionRate,
                                              'total_value' => round($item->total_value, 2)
                                          ];
                                      })
                                      ->sortByDesc('conversion_rate')
                                      ->values();

        // 🎯 ENTONNOIR DE CONVERSION
        $funnelData = collect([
            ['name' => 'Investisseurs créés', 'count' => Investisseur::count()],
            ['name' => 'Investisseurs actifs', 'count' => Investisseur::where('statut', '!=', 'inactif')->count()],
            ['name' => 'En négociation', 'count' => Investisseur::where('statut', 'negociation')->count()],
            ['name' => 'Engagés', 'count' => Investisseur::where('statut', 'engagement')->count()],
            ['name' => 'En finalisation', 'count' => Investisseur::where('statut', 'finalisation')->count()],
            ['name' => 'Investis', 'count' => Investisseur::where('statut', 'investi')->count()]
        ])->map(function ($etape) {
            return [
                'name' => $etape['name'],
                'value' => $etape['count']
            ];
        });

        // 📊 ROI ANALYSIS
        $roiData = Investisseur::where('statut', 'investi')
                               ->whereNotNull('date_signature')
                               ->select(
                                   'nom',
                                   'montant_investissement',
                                   'created_at',
                                   'date_signature',
                                   DB::raw('DATEDIFF(date_signature, created_at) as jours_conversion')
                               )
                               ->get()
                               ->map(function ($item) {
                                   return [
                                       'name' => $item->nom,
                                       'montant' => round($item->montant_investissement, 2),
                                       'jours_conversion' => $item->jours_conversion,
                                       'roi_potentiel' => round($item->montant_investissement * 0.15, 2)
                                   ];
                               })
                               ->sortByDesc('montant')
                               ->take(20)
                               ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_investisseurs' => $totalInvestisseurs,
                    'nouveau_ce_mois' => $nouveauCeMois,
                    'total_investi' => $totalInvesti,
                    'taux_conversion' => $tauxConversion,
                    'montant_total' => round($montantTotal, 2),
                    'montant_signe' => round($montantSigne, 2),
                    'actifs_pipeline' => Investisseur::whereIn('statut', ['actif', 'negociation', 'engagement'])->count(),
                    'en_finalisation' => Investisseur::where('statut', 'finalisation')->count()
                ],
                'charts' => [
                    'status' => $statusData,
                    'pipeline' => $pipelineData,
                    'evolution' => $evolutionData,
                    'investment_analysis' => $investmentData,
                    'sectors' => $sectorData,
                    'countries' => $countryData,
                    'responsables' => $responsableData,
                    'conversion_funnel' => $funnelData,
                    'roi_analysis' => $roiData
                ]
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Statistiques globales
 */
public function statsGlobal()
{
    try {
        $totalInvestisseurs = Investisseur::count();
        $aujourdhui = Investisseur::whereDate('created_at', today())->count();
        $cetteSemaine = Investisseur::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $ceMois = Investisseur::whereMonth('created_at', now()->month)->count();
        
        // Investissements
        $totalInvestissement = Investisseur::sum('montant_investissement');
        $investissementSigne = Investisseur::whereNotNull('date_signature')->sum('montant_investissement');
        $investissementMoyen = Investisseur::whereNotNull('montant_investissement')->avg('montant_investissement');
        
        // Conversions
        $convertisProjet = Investisseur::where('statut', 'investi')->count();
        $tauxConversion = $totalInvestisseurs > 0 ? round(($convertisProjet / $totalInvestisseurs) * 100, 2) : 0;
        
        // En cours
        $enNegociation = Investisseur::where('statut', 'negociation')->count();
        $enPipeline = Investisseur::whereNotNull('pipeline_stage_id')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_investisseurs' => $totalInvestisseurs,
                'nouveaux_aujourdhui' => $aujourdhui,
                'nouveaux_cette_semaine' => $cetteSemaine,
                'nouveaux_ce_mois' => $ceMois,
                'total_investissement' => round($totalInvestissement, 2),
                'investissement_signe' => round($investissementSigne, 2),
                'investissement_moyen' => round($investissementMoyen, 2),
                'convertis_projet' => $convertisProjet,
                'taux_conversion' => $tauxConversion,
                'en_negociation' => $enNegociation,
                'en_pipeline' => $enPipeline,
                'taux_signature' => $totalInvestisseurs > 0 ? round((Investisseur::whereNotNull('date_signature')->count() / $totalInvestisseurs) * 100, 2) : 0
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Répartition des investisseurs par statut
 */
public function chartByStatus()
{
    try {
        $data = Investisseur::select('statut', DB::raw('COUNT(*) as count'))
                           ->groupBy('statut')
                           ->orderByDesc('count')
                           ->get()
                           ->map(function ($item) {
                               return [
                                   'name' => $this->getStatusLabel($item->statut),
                                   'value' => $item->count,
                                   'code' => $item->statut,
                                   'color' => $this->getStatusColor($item->statut)
                               ];
                           });

        return response()->json([
            'success' => true,
            'data' => $data,
            'chart_type' => 'pie'
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Progression dans le pipeline (Funnel Chart)
 */
public function chartPipelineProgression()
{
    try {
        $data = InvestorPipelineStage::leftJoin('investor_pipeline_progressions', 'investor_pipeline_stages.id', '=', 'investor_pipeline_progressions.stage_id')
                                    ->leftJoin('investisseurs', 'investor_pipeline_progressions.investisseur_id', '=', 'investisseurs.id')
                                    ->select(
                                        'investor_pipeline_stages.name as stage_name',
                                        'investor_pipeline_stages.order',
                                        DB::raw('COUNT(DISTINCT investisseurs.id) as count')
                                    )
                                    ->where('investor_pipeline_stages.is_active', true)
                                    ->whereNull('investisseurs.deleted_at')
                                    ->groupBy('investor_pipeline_stages.id', 'investor_pipeline_stages.name', 'investor_pipeline_stages.order')
                                    ->orderBy('investor_pipeline_stages.order')
                                    ->get()
                                    ->map(function ($item) {
                                        return [
                                            'name' => $item->stage_name,
                                            'value' => $item->count,
                                            'order' => $item->order
                                        ];
                                    });

        return response()->json([
            'success' => true,
            'data' => $data,
            'chart_type' => 'funnel'
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Évolution mensuelle des investisseurs
 */
public function chartEvolutionMensuelle()
{
    try {
        $data = Investisseur::select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN statut = "investi" THEN 1 ELSE 0 END) as convertis'),
                    DB::raw('SUM(montant_investissement) as total_investment'),
                    DB::raw('SUM(CASE WHEN date_signature IS NOT NULL THEN montant_investissement ELSE 0 END) as signed_investment')
                )
                ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    $date = Carbon::createFromDate($item->year, $item->month, 1);
                    return [
                        'name' => $date->format('M Y'),
                        'period' => $date->format('Y-m'),
                        'total' => $item->total,
                        'convertis' => $item->convertis,
                        'total_investment' => round($item->total_investment, 2),
                        'signed_investment' => round($item->signed_investment, 2),
                        'conversion_rate' => $item->total > 0 ? round(($item->convertis / $item->total) * 100, 1) : 0
                    ];
                });

        return response()->json([
            'success' => true,
            'data' => $data,
            'chart_type' => 'line'
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Analyse des investissements par tranche
 */
public function chartInvestmentAnalysis()
{
    try {
        $tranches = [
            ['min' => 0, 'max' => 50000, 'label' => '0-50K'],
            ['min' => 50000, 'max' => 100000, 'label' => '50K-100K'],
            ['min' => 100000, 'max' => 500000, 'label' => '100K-500K'],
            ['min' => 500000, 'max' => 1000000, 'label' => '500K-1M'],
            ['min' => 1000000, 'max' => null, 'label' => '1M+']
        ];

        $data = collect($tranches)->map(function ($tranche) {
            $query = Investisseur::where('montant_investissement', '>=', $tranche['min']);
            if ($tranche['max']) {
                $query->where('montant_investissement', '<', $tranche['max']);
            }
            
            $count = $query->count();
            $totalValue = $query->sum('montant_investissement');
            $convertis = $query->where('statut', 'investi')->count();
            
            return [
                'name' => $tranche['label'],
                'value' => $count,
                'total_value' => round($totalValue, 2),
                'convertis' => $convertis,
                'conversion_rate' => $count > 0 ? round(($convertis / $count) * 100, 1) : 0
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'chart_type' => 'bar'
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Entonnoir de conversion
 */
public function chartConversionFunnel()
{
    try {
        $etapes = [
            ['name' => 'Investisseurs créés', 'count' => Investisseur::count()],
            ['name' => 'Investisseurs actifs', 'count' => Investisseur::where('statut', '!=', 'inactif')->count()],
            ['name' => 'En négociation', 'count' => Investisseur::where('statut', 'negociation')->count()],
            ['name' => 'Engagés', 'count' => Investisseur::where('statut', 'engagement')->count()],
            ['name' => 'En finalisation', 'count' => Investisseur::where('statut', 'finalisation')->count()],
            ['name' => 'Investis', 'count' => Investisseur::where('statut', 'investi')->count()]
        ];

        $data = collect($etapes)->map(function ($etape) {
            return [
                'name' => $etape['name'],
                'value' => $etape['count']
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'chart_type' => 'funnel'
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Analyse ROI et temps de conversion
 */
public function chartROIAnalysis()
{
    try {
        $data = Investisseur::where('statut', 'investi')
                           ->whereNotNull('date_signature')
                           ->select(
                               'id',
                               'nom',
                               'montant_investissement',
                               'created_at',
                               'date_signature',
                               DB::raw('DATEDIFF(date_signature, created_at) as jours_conversion')
                           )
                           ->get()
                           ->map(function ($item) {
                               return [
                                   'name' => $item->nom,
                                   'montant' => round($item->montant_investissement, 2),
                                   'jours_conversion' => $item->jours_conversion,
                                   'roi_potentiel' => round($item->montant_investissement * 0.15, 2) // Estimation 15% ROI
                               ];
                           })
                           ->sortByDesc('montant')
                           ->take(20)
                           ->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'chart_type' => 'scatter'
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Heatmap d'activité par jour/heure
 */
public function chartActivityHeatmap()
{
    try {
        $data = Investisseur::select(
                    DB::raw('DAYOFWEEK(created_at) as day_of_week'),
                    DB::raw('HOUR(created_at) as hour'),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('day_of_week', 'hour')
                ->get()
                ->map(function ($item) {
                    $days = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
                    return [
                        'day' => $days[$item->day_of_week - 1],
                        'hour' => $item->hour,
                        'value' => $item->count,
                        'coordinates' => [$item->day_of_week - 1, $item->hour]
                    ];
                });

        return response()->json([
            'success' => true,
            'data' => $data,
            'chart_type' => 'heatmap'
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Répartition par secteur
 */
public function chartBySector()
{
    try {
        $data = Investisseur::join('secteurs', 'investisseurs.secteur_id', '=', 'secteurs.id')
                           ->select(
                               'secteurs.name as secteur',
                               DB::raw('COUNT(investisseurs.id) as count'),
                               DB::raw('SUM(investisseurs.montant_investissement) as total_investment'),
                               DB::raw('AVG(investisseurs.montant_investissement) as avg_investment')
                           )
                           ->groupBy('secteurs.name')
                           ->orderByDesc('total_investment')
                           ->limit(10)
                           ->get()
                           ->map(function ($item) {
                               return [
                                   'name' => $item->secteur, // ✅ Utilise 'name' au lieu de 'label'
                                   'value' => $item->count,
                                   'total_investment' => round($item->total_investment, 2),
                                   'avg_investment' => round($item->avg_investment, 2)
                               ];
                           });

        return response()->json([
            'success' => true,
            'data' => $data,
            'chart_type' => 'horizontal_bar'
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Répartition par pays
 */
public function chartByCountry()
{
    try {
        $data = Investisseur::join('pays', 'investisseurs.pays_id', '=', 'pays.id')
                           ->select(
                               'pays.name_pays as country',
                               DB::raw('COUNT(investisseurs.id) as count'),
                               DB::raw('SUM(investisseurs.montant_investissement) as total_investment')
                           )
                           ->groupBy('pays.name_pays')
                           ->orderByDesc('count')
                           ->limit(8)
                           ->get()
                           ->map(function ($item) {
                               return [
                                   'name' => $item->country, // ✅ Utilise 'name' au lieu de 'label'
                                   'value' => $item->count,
                                   'total_investment' => round($item->total_investment, 2)
                               ];
                           });

        return response()->json([
            'success' => true,
            'data' => $data,
            'chart_type' => 'pie'
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Performance par responsable
 */
public function chartByResponsable()
{
    try {
        $data = Investisseur::join('users', 'investisseurs.responsable_id', '=', 'users.id')
                           ->select(
                               'users.name as responsable',
                               DB::raw('COUNT(investisseurs.id) as total'),
                               DB::raw('SUM(CASE WHEN investisseurs.statut = "investi" THEN 1 ELSE 0 END) as convertis'),
                               DB::raw('SUM(investisseurs.montant_investissement) as total_value'),
                               DB::raw('SUM(CASE WHEN investisseurs.date_signature IS NOT NULL THEN investisseurs.montant_investissement ELSE 0 END) as signed_value')
                           )
                           ->groupBy('users.name')
                           ->having('total', '>', 0)
                           ->get()
                           ->map(function ($item) {
                               $conversionRate = $item->total > 0 ? round(($item->convertis / $item->total) * 100, 1) : 0;
                               return [
                                   'name' => $item->responsable, // ✅ Utilise 'name' au lieu de 'label'
                                   'value' => $item->total,
                                   'convertis' => $item->convertis,
                                   'conversion_rate' => $conversionRate,
                                   'total_value' => round($item->total_value, 2),
                                   'signed_value' => round($item->signed_value, 2),
                                   'avg_deal_size' => $item->total > 0 ? round($item->total_value / $item->total, 2) : 0
                               ];
                           })
                           ->sortByDesc('conversion_rate')
                           ->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'chart_type' => 'bar'
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Obtenir le libellé du statut
 */
private function getStatusLabel($statut)
{
    $labels = [
        'actif' => 'Actif',
        'negociation' => 'En négociation',
        'engagement' => 'Engagé',
        'finalisation' => 'En finalisation',
        'investi' => 'Investi',
        'suspendu' => 'Suspendu',
        'inactif' => 'Inactif'
    ];
    
    return $labels[$statut] ?? ucfirst($statut);
}

/**
 * Obtenir la couleur du statut
 */
private function getStatusColor($statut)
{
    $colors = [
        'actif' => '#3B82F6',
        'negociation' => '#F59E0B',
        'engagement' => '#8B5CF6',
        'finalisation' => '#06B6D4',
        'investi' => '#059669',
        'suspendu' => '#EF4444',
        'inactif' => '#6B7280'
    ];
    
    return $colors[$statut] ?? '#6B7280';
}

/**
 * Statistiques de résumé
 */
private function getSummaryStats()
{
    return [
        'total_investisseurs' => Investisseur::count(),
        'nouveau_ce_mois' => Investisseur::whereMonth('created_at', now()->month)->count(),
        'total_investi' => Investisseur::where('statut', 'investi')->count(),
        'taux_conversion' => $this->calculateConversionRate(),
        'montant_total' => Investisseur::sum('montant_investissement'),
        'montant_signe' => Investisseur::whereNotNull('date_signature')->sum('montant_investissement'),
        'actifs_pipeline' => Investisseur::whereIn('statut', ['actif', 'negociation', 'engagement'])->count(),
        'en_finalisation' => Investisseur::where('statut', 'finalisation')->count()
    ];
}

/**
 * Calculer le taux de conversion
 */
private function calculateConversionRate()
{
    $total = Investisseur::count();
    $converted = Investisseur::where('statut', 'investi')->count();
    
    return $total > 0 ? round(($converted / $total) * 100, 2) : 0;
}
}