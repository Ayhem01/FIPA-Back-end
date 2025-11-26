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
use App\Services\BlockchainTxLogger; // ✅ Import BlockchainTxLogger
use App\Services\BlockchainService; // ✅ Import BlockchainService


class ProspectController extends Controller
{
    /**
     * Afficher la liste des prospects
     */
    private function mapProspectStatusToBlockchain(string $statut): int
    {
        switch ($statut) {
            case 'nouveau':
                return 0;
            case 'en_cours':
                return 1;
            case 'qualifie':
                return 2;
            case 'non_qualifie':
                return 3;
            case 'converti':
                return 4;
            case 'perdu':
                return 5;
            default:
                return 0;
        }
    }
    public function index(Request $request)
    {
        try {
            $query = Prospect::with(['entreprise', 'pays', 'secteur', 'responsable']);

            // Filtres
            if ($request->has('statut')) {
                $query->where('statut', $request->statut);
            }

            if ($request->has('secteur_id')) {
                $query->where('secteur_id', $request->secteur_id);
            }

            if ($request->has('pays_id')) {
                $query->where('pays_id', $request->pays_id);
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
                    'errors' => $validator->errors(),
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
    public function showOnChain($id)
{
    try {
        $res = app(\App\Services\BlockchainService::class)->getProspectOnChain((int)$id);
        return response()->json([
            'success' => true,
            'data' => $res['data'] ?? $res
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Prospect non trouvé sur la blockchain',
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

            // --- Blockchain logging & MS call ---
            $tx = \App\Services\BlockchainTxLogger::start('update_prospect', 'prospect', $prospect->id, [
                'name'                => $prospect->nom,
                'description'         => (string)($prospect->description ?? ''),
                'valeurPotentielle'   => (int)($prospect->valeur_potentielle ?? 0),
                'status'              => $this->mapProspectStatusToBlockchain($prospect->statut ?? 'nouveau'),
                'responsiblePerson'   => (int)($prospect->responsable_id ?? 0),
            ]);

            try {
                $res = app(\App\Services\BlockchainService::class)->updateProspect(
                    $prospect->id,
                    $prospect->nom,
                    (string)($prospect->description ?? ''),
                    (int)($prospect->valeur_potentielle ?? 0),
                    $this->mapProspectStatusToBlockchain($prospect->statut ?? 'nouveau'),
                    (int)($prospect->responsable_id ?? 0)
                );
                \App\Services\BlockchainTxLogger::success($tx, $res);
            } catch (\Throwable $e) {
                \App\Services\BlockchainTxLogger::fail($tx, $e->getMessage());
                \Log::warning('Blockchain updateProspect failed', [
                    'prospect_id' => $prospect->id,
                    'error' => $e->getMessage()
                ]);
            }

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

        // Traçabilité blockchain
        $tx = \App\Services\BlockchainTxLogger::start('delete_prospect', 'prospect', $prospect->id, []);

        try {
            $res = app(\App\Services\BlockchainService::class)->deleteProspect($prospect->id);
            \App\Services\BlockchainTxLogger::success($tx, $res);
        } catch (\Throwable $e) {
            \App\Services\BlockchainTxLogger::fail($tx, $e->getMessage());
            \Log::warning('Blockchain deleteProspect failed', [
                'prospect_id' => $prospect->id,
                'error' => $e->getMessage()
            ]);
        }

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
                    'progression_percentage' => $prospect->progressionPercentage(),
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
            'date_signature' => 'nullable|date',
            'statut' => 'nullable|in:actif,negociation,engagement,finalisation,investi,suspendu,inactif'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $validator->errors()
            ], 422);
        }

        $prospect = Prospect::with(['entreprise', 'secteur', 'pays'])->findOrFail($id);
        $userId = Auth::id();

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

        // --- PRÉPARATION DES DONNÉES ---
        $nom = $request->input('nom');
        $montantInvestissement = (int)($request->input('montant_investissement') ?? 0);
        $interetsSpecifiques = (string)($request->input('interets_specifiques') ?? '');
        $criteresInvestissement = (string)($request->input('criteres_investissement') ?? '');
        $statut = $request->input('statut') ?? 'actif';

        // ========================================
        // 1️⃣ BLOCKCHAIN: CONVERTIR LE PROSPECT EN INVESTISSEUR
        // ========================================
        $tx1 = BlockchainTxLogger::start('convert_prospect_to_investisseur', 'prospect', $prospect->id, [
            'prospectId' => (int)$prospect->id,
            'nom' => $nom,
            'montant_investissement' => $montantInvestissement,
            'interets_specifiques' => $interetsSpecifiques,
            'criteres_investissement' => $criteresInvestissement,
            'statut' => $statut
        ]);

        $investisseurIdFromConversion = null;
        $conversionTxHash = null;
        $conversionBlockNumber = null;

        try {
            $service = app(BlockchainService::class);
            
            // Appel MS: POST /api/prospect/:prospectId/convert-investisseur
            $resConvert = $service->convertProspectToInvestisseur(
                prospectId: (int)$prospect->id,
                nom: $nom,
                montantInvestissement: $montantInvestissement,
                interetsSpecifiques: $interetsSpecifiques,
                criteresInvestissement: $criteresInvestissement,
                statut: $statut
            );
            
            BlockchainTxLogger::success($tx1, $resConvert);
            
            $investisseurIdFromConversion = $resConvert['data']['investisseurId'] ?? null;
            $conversionTxHash = $resConvert['data']['transactionHash'] ?? null;
            $conversionBlockNumber = $resConvert['data']['blockNumber'] ?? null;
            
            \Log::info('Conversion prospect → investisseur réussie', [
                'prospect_id' => $prospect->id,
                'investisseur_id_chain' => $investisseurIdFromConversion,
                'tx_hash' => $conversionTxHash
            ]);
            
        } catch (\Throwable $e) {
            BlockchainTxLogger::fail($tx1, $e->getMessage());
            \Log::error('Blockchain conversion prospect → investisseur failed', [
                'prospect_id' => $prospect->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Ne pas bloquer la conversion locale
        }

        // ========================================
        // 2️⃣ BLOCKCHAIN: CRÉER L'INVESTISSEUR
        // ========================================
        $tx2 = BlockchainTxLogger::start('create_investisseur', 'prospect', $prospect->id, [
            'nom' => $nom,
            'prospect_id' => (int)$prospect->id,
            'montant_investissement' => $montantInvestissement,
            'interets_specifiques' => $interetsSpecifiques,
            'criteres_investissement' => $criteresInvestissement,
            'statut' => $statut
        ]);

        $investisseurIdFromCreation = null;
        $creationTxHash = null;
        $creationBlockNumber = null;

        try {
            $service = app(BlockchainService::class);
            
            // ✅ Appel route POST /api/investisseur
            $resCreate = $service->createInvestisseurOnChain(
                nom: $nom,
                prospectId: (int)$prospect->id,
                montantInvestissement: $montantInvestissement,
                interetsSpecifiques: $interetsSpecifiques,
                criteresInvestissement: $criteresInvestissement,
                statut: $statut
            );
            
            BlockchainTxLogger::success($tx2, $resCreate);
            
            $investisseurIdFromCreation = $resCreate['data']['investisseurId'] ?? null;
            $creationTxHash = $resCreate['data']['transactionHash'] ?? null;
            $creationBlockNumber = $resCreate['data']['blockNumber'] ?? null;
            
            \Log::info('Création investisseur réussie', [
                'prospect_id' => $prospect->id,
                'investisseur_id_chain' => $investisseurIdFromCreation,
                'tx_hash' => $creationTxHash
            ]);
            
        } catch (\Throwable $e) {
            BlockchainTxLogger::fail($tx2, $e->getMessage());
            \Log::error('Blockchain creation investisseur failed', [
                'prospect_id' => $prospect->id,
                'error' => $e->getMessage()
            ]);
        }

        // ========================================
        // 3️⃣ BASE DE DONNÉES: CRÉATION DE L'INVESTISSEUR
        // ========================================
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
                'nom' => $nom,
                'prospect_id' => $prospect->id,
                'email' => $prospect->email,
                'telephone' => $prospect->telephone,
                'adresse' => $prospect->adresse,
                'pays_id' => $prospect->pays_id,
                'secteur_id' => $prospect->secteur_id,
                'montant_investissement' => $montantInvestissement,
                'devise' => $request->input('devise', 'EUR'),
                'interets_specifiques' => $interetsSpecifiques,
                'criteres_investissement' => $criteresInvestissement,
                'statut' => $statut,
                'date_engagement' => $request->input('date_engagement'),
                'date_signature' => $request->input('date_signature'),
                'responsable_id' => $request->input('responsable_id') ?? $prospect->responsable_id,
                'created_by' => $userId,
                'notes_internes' => $request->input('notes') ?? "Investisseur créé à partir du prospect #{$prospect->id}",
                'date_dernier_contact' => $prospect->date_dernier_contact,
                'prochain_contact_prevu' => $prospect->prochain_contact_prevu,
                // Infos blockchain (TX de création)
                'tx_hash' => $creationTxHash,
                'tx_block_number' => $creationBlockNumber,
            ]);

            // 3. Initialiser le pipeline de l'investisseur
            $investorPipelineInitialized = false;
            $firstStage = InvestorPipelineStage::where('is_active', true)
                ->orderBy('order')
                ->first();

            if ($firstStage) {
                $investisseur->update(['pipeline_stage_id' => $firstStage->id]);

                InvestorPipelineProgression::create([
                    'investisseur_id' => $investisseur->id,
                    'stage_id' => $firstStage->id,
                    'completed' => false,
                    'assigned_to' => $userId,
                    'notes' => 'Étape initiale créée automatiquement lors de la conversion'
                ]);

                $investorPipelineInitialized = true;
            }

            // 4. Marquer le prospect comme converti (TX de conversion)
            $prospect->update([
                'statut' => 'converti',
                'converted_at' => now(),
                'converted_to_id' => $investisseur->id,
                'pipeline_completed_at' => now(),
                'pipeline_completed_by' => $userId,
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
                    'pipeline_initialized' => $investorPipelineInitialized,
                    'pipeline_completed' => true,
                    'final_stage_completed' => true,
                    'progression_percentage' => $prospect->progressionPercentage(),
                    'initial_stage' => $firstStage ? $firstStage->name : null
                ],
                'blockchain_info' => [
                    'conversion' => [
                        'investisseur_id' => $investisseurIdFromConversion,
                        'tx_hash' => $conversionTxHash,
                        'block_number' => $conversionBlockNumber
                    ],
                    'creation' => [
                        'investisseur_id' => $investisseurIdFromCreation,
                        'tx_hash' => $creationTxHash,
                        'block_number' => $creationBlockNumber
                    ]
                ]
            ]
        ], 201);
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

        // Mettre à jour le statut localement
        $prospect->update([
            'statut' => $validated['statut']
        ]);

        // Traçabilité blockchain
        $statusNum = $this->mapProspectStatusToBlockchain($validated['statut']);
        $tx = \App\Services\BlockchainTxLogger::start('update_prospect_status', 'prospect', $prospect->id, [
            'status' => $statusNum,
        ]);

        try {
            $res = app(\App\Services\BlockchainService::class)->updateProspectStatus(
                $prospect->id,
                $statusNum
            );
            \App\Services\BlockchainTxLogger::success($tx, $res);
        } catch (\Throwable $e) {
            \App\Services\BlockchainTxLogger::fail($tx, $e->getMessage());
            \Log::warning('Blockchain updateProspectStatus failed', [
                'prospect_id' => $prospect->id,
                'error' => $e->getMessage()
            ]);
        }

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
            $byStage = ProspectPipelineStage::withCount(['progressions as count' => function ($q) {
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
    public function showOnChainTasks($id)
{
    try {
        $res = app(\App\Services\BlockchainService::class)->getProspectTasksOnChain((int)$id);
        return response()->json([
            'success' => true,
            'data' => $res['data'] ?? $res
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Tâches non trouvées sur la blockchain',
            'error' => $e->getMessage()
        ], 404);
    }
}
public function showOnChainStageTasks($prospectId, $stageId)
{
    try {
        $res = app(\App\Services\BlockchainService::class)
            ->getProspectStageTasksOnChain((int)$prospectId, (int)$stageId);

        return response()->json([
            'success' => true,
            'data' => $res['data'] ?? $res
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Tâches non trouvées sur la blockchain',
            'error' => $e->getMessage()
        ], 404);
    }
}
public function showOnChainProgress($id)
{
    try {
        $res = app(\App\Services\BlockchainService::class)->getProspectProgressOnChain((int)$id);
        return response()->json([
            'success' => true,
            'data' => $res['data'] ?? $res
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Progression non trouvée sur la blockchain',
            'error' => $e->getMessage()
        ], 404);
    }
}


    public function dashboard()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $this->getSummaryStats(),
                    'charts' => [
                        'status' => $this->getStatusChart(),
                        'pipeline' => $this->getPipelineChart(),
                        'evolution' => $this->getEvolutionChart(),
                        'conversion' => $this->getConversionChart(),
                        'sectors' => $this->getSectorChart(),
                        'countries' => $this->getCountryChart(),
                        'responsables' => $this->getResponsableChart()
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
     * Graphique de répartition par statut
     */
    public function chartByStatus()
    {
        try {
            $data = Prospect::selectRaw('statut, COUNT(*) as count')
                ->groupBy('statut')
                ->get()
                ->map(function ($item) {
                    return [
                        'name' => $this->getStatusLabel($item->statut), // ✅ Changé de 'label' à 'name'
                        'value' => $item->count, // ✅ Structure ECharts
                        'code' => $item->statut,
                        'color' => $this->getStatusColor($item->statut)
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => $data->sum('value'),
                'chart_type' => 'pie' // ✅ Ajouté le type de chart
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Graphique de répartition par secteur
     */
    public function chartBySector()
    {
        try {
            $data = Prospect::with('secteur')
                ->selectRaw('secteur_id, COUNT(*) as count, AVG(valeur_potentielle) as avg_value')
                ->whereNotNull('secteur_id')
                ->groupBy('secteur_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'label' => $item->secteur->nom ?? 'Non défini',
                        'count' => $item->count,
                        'avg_value' => round($item->avg_value, 2),
                        'total_value' => Prospect::where('secteur_id', $item->secteur_id)->sum('valeur_potentielle')
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Graphique de répartition par pays
     */
    public function chartByCountry()
    {
        try {
            $data = Prospect::with('pays')
                ->selectRaw('pays_id, COUNT(*) as count')
                ->whereNotNull('pays_id')
                ->groupBy('pays_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'label' => $item->pays->nom ?? 'Non défini',
                        'count' => $item->count,
                        'percentage' => round(($item->count / Prospect::count()) * 100, 1)
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function chartEvolutionMensuelle()
    {
        try {
            $data = Prospect::selectRaw('
                DATE_FORMAT(created_at, "%Y-%m") as period,
                COUNT(*) as total,
                SUM(CASE WHEN statut = "converti" THEN 1 ELSE 0 END) as converted,
                SUM(valeur_potentielle) as potential_value
            ')
                ->where('created_at', '>=', now()->subMonths(12))
                ->groupBy('period')
                ->orderBy('period')
                ->get()
                ->map(function ($item) {
                    return [
                        'name' => $item->period, // ✅ Pour l'axe X
                        'period' => $item->period,
                        'total' => $item->total,
                        'converted' => $item->converted,
                        'conversion_rate' => $item->total > 0 ? round(($item->converted / $item->total) * 100, 1) : 0,
                        'potential_value' => round($item->potential_value, 2)
                    ];
                })
                ->values(); // ✅ Réindexer

            return response()->json([
                'success' => true,
                'data' => $data,
                'chart_type' => 'line'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function chartPipelineProgression()
    {
        try {
            $stages = ProspectPipelineStage::with(['progressions' => function ($query) {
                $query->whereHas('prospect', function ($q) {
                    $q->whereNull('deleted_at');
                });
            }])
                ->where('is_active', true)
                ->orderBy('order')
                ->get()
                ->map(function ($stage) {
                    $totalProspects = $stage->progressions->count();
                    $completedProspects = $stage->progressions->where('completed', true)->count();

                    return [
                        'stage_name' => $stage->name,
                        'order' => $stage->order,
                        'total_prospects' => $totalProspects,
                        'completed_prospects' => $completedProspects,
                        'completion_rate' => $totalProspects > 0 ? round(($completedProspects / $totalProspects) * 100, 1) : 0,
                        'is_final' => $stage->is_final
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $stages
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Taux de conversion
     */
    public function chartConversionRate()
    {
        try {
            $totalProspects = Prospect::count();
            $convertedProspects = Prospect::where('statut', 'converti')->count();
            $lostProspects = Prospect::where('statut', 'perdu')->count();
            $qualifiedProspects = Prospect::where('statut', 'qualifie')->count();

            $data = [
                'total_prospects' => $totalProspects,
                'converted' => $convertedProspects,
                'lost' => $lostProspects,
                'qualified' => $qualifiedProspects,
                'conversion_rate' => $totalProspects > 0 ? round(($convertedProspects / $totalProspects) * 100, 2) : 0,
                'loss_rate' => $totalProspects > 0 ? round(($lostProspects / $totalProspects) * 100, 2) : 0,
                'qualification_rate' => $totalProspects > 0 ? round(($qualifiedProspects / $totalProspects) * 100, 2) : 0
            ];

            // Conversion par mois
            $monthlyConversion = Prospect::selectRaw('
                DATE_FORMAT(created_at, "%Y-%m") as month,
                COUNT(*) as total,
                SUM(CASE WHEN statut = "converti" THEN 1 ELSE 0 END) as converted
            ')
                ->where('created_at', '>=', now()->subMonths(6))
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return [
                        'month' => $item->month,
                        'total' => $item->total,
                        'converted' => $item->converted,
                        'rate' => $item->total > 0 ? round(($item->converted / $item->total) * 100, 1) : 0
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $data,
                    'monthly_trend' => $monthlyConversion
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function chartByResponsable()
    {
        try {
            $data = Prospect::with('responsable')
                ->selectRaw('
                responsable_id,
                COUNT(*) as total_prospects,
                SUM(CASE WHEN statut = "converti" THEN 1 ELSE 0 END) as converted,
                SUM(valeur_potentielle) as total_value,
                AVG(valeur_potentielle) as avg_value
            ')
                ->whereNotNull('responsable_id')
                ->groupBy('responsable_id')
                ->having('total_prospects', '>', 0)
                ->get()
                ->map(function ($item) {
                    return [
                        'name' => $item->responsable->name ?? 'Non défini', // ✅ Structure ECharts
                        'value' => $item->total_prospects, // ✅ Valeur principale
                        'total_prospects' => $item->total_prospects,
                        'converted' => $item->converted,
                        'conversion_rate' => round(($item->converted / $item->total_prospects) * 100, 1),
                        'total_value' => round($item->total_value, 2),
                        'avg_value' => round($item->avg_value, 2)
                    ];
                })
                ->sortByDesc('conversion_rate')
                ->values(); // ✅ Important pour réindexer le tableau

            return response()->json([
                'success' => true,
                'data' => $data,
                'chart_type' => 'bar'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Analyse de la valeur potentielle
     */
    public function chartValueAnalysis()
    {
        try {
            $totalValue = Prospect::sum('valeur_potentielle');
            $avgValue = Prospect::avg('valeur_potentielle');

            // Répartition par tranche de valeur
            $valueRanges = [
                ['min' => 0, 'max' => 10000, 'label' => '0-10K'],
                ['min' => 10000, 'max' => 50000, 'label' => '10K-50K'],
                ['min' => 50000, 'max' => 100000, 'label' => '50K-100K'],
                ['min' => 100000, 'max' => 500000, 'label' => '100K-500K'],
                ['min' => 500000, 'max' => null, 'label' => '500K+']
            ];

            $rangeData = collect($valueRanges)->map(function ($range) {
                $query = Prospect::where('valeur_potentielle', '>=', $range['min']);
                if ($range['max']) {
                    $query->where('valeur_potentielle', '<', $range['max']);
                }

                return [
                    'range' => $range['label'],
                    'count' => $query->count(),
                    'total_value' => $query->sum('valeur_potentielle')
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_value' => round($totalValue, 2),
                        'average_value' => round($avgValue, 2),
                        'prospects_with_value' => Prospect::whereNotNull('valeur_potentielle')->where('valeur_potentielle', '>', 0)->count()
                    ],
                    'ranges' => $rangeData
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Statistiques de résumé
     */
    private function getSummaryStats()
    {
        return [
            'total_prospects' => Prospect::count(),
            'new_this_month' => Prospect::whereMonth('created_at', now()->month)->count(),
            'converted_total' => Prospect::where('statut', 'converti')->count(),
            'conversion_rate' => $this->calculateConversionRate(),
            'total_potential_value' => Prospect::sum('valeur_potentielle'),
            'avg_potential_value' => Prospect::avg('valeur_potentielle'),
            'active_prospects' => Prospect::whereIn('statut', ['nouveau', 'en_cours', 'qualifie'])->count(),
            'prospects_in_pipeline' => Prospect::whereNotNull('pipeline_stage_id')->count()
        ];
    }

    /**
     * Calculer le taux de conversion
     */
    private function calculateConversionRate()
    {
        $total = Prospect::count();
        $converted = Prospect::where('statut', 'converti')->count();

        return $total > 0 ? round(($converted / $total) * 100, 2) : 0;
    }

    /**
     * Obtenir le libellé du statut
     */
    private function getStatusLabel($statut)
    {
        $labels = [
            'nouveau' => 'Nouveau',
            'en_cours' => 'En cours',
            'qualifie' => 'Qualifié',
            'non_qualifie' => 'Non qualifié',
            'converti' => 'Converti',
            'perdu' => 'Perdu'
        ];

        return $labels[$statut] ?? $statut;
    }

    /**
     * Obtenir la couleur du statut
     */
    private function getStatusColor($statut)
    {
        $colors = [
            'nouveau' => '#3B82F6',
            'en_cours' => '#F59E0B',
            'qualifie' => '#10B981',
            'non_qualifie' => '#6B7280',
            'converti' => '#059669',
            'perdu' => '#EF4444'
        ];

        return $colors[$statut] ?? '#6B7280';
    }

    /**
     * Temps moyen de conversion par étape
     */

    public function chartConversionTimeAnalysis()
    {
        try {
            $data = ProspectPipelineStage::where('is_active', true)
                ->orderBy('order')
                ->get()
                ->map(function ($stage) {
                    // ✅ Requête directe sur les progressions
                    $progressions = DB::table('prospect_pipeline_progressions as ppp')
                        ->join('prospects as p', 'ppp.prospect_id', '=', 'p.id')
                        ->where('ppp.stage_id', $stage->id)
                        ->where('ppp.completed', true)
                        ->whereNotNull('ppp.completed_at')
                        ->whereNull('p.deleted_at')
                        ->select([
                            'ppp.created_at',
                            'ppp.completed_at',
                            DB::raw('DATEDIFF(ppp.completed_at, ppp.created_at) as duration_days')
                        ])
                        ->get();

                    $avgTime = 0;
                    $totalProspects = $progressions->count();

                    if ($totalProspects > 0) {
                        $avgTime = round($progressions->avg('duration_days'), 1);
                    }

                    return [
                        'name' => $stage->name,
                        'avg_days' => $avgTime,
                        'prospects_count' => $totalProspects,
                        'order' => $stage->order,
                        'min_days' => $totalProspects > 0 ? $progressions->min('duration_days') : 0,
                        'max_days' => $totalProspects > 0 ? $progressions->max('duration_days') : 0,
                        'median_days' => $totalProspects > 0 ? $this->calculateMedian($progressions->pluck('duration_days')) : 0
                    ];
                })
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
     * Calculer la médiane
     */
    private function calculateMedian($values)
    {
        $sorted = $values->sort()->values();
        $count = $sorted->count();

        if ($count === 0) return 0;
        if ($count === 1) return $sorted[0];

        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return ($sorted[$middle - 1] + $sorted[$middle]) / 2;
        }

        return $sorted[$middle];
    }

    /**
     * Analyse des prospects perdus par raison
     */
    // public function chartLostProspectsAnalysis()
    // {
    //     try {
    //         // Vous devrez ajouter une colonne 'reason_lost' à votre table prospects
    //         $data = Prospect::where('statut', 'perdu')
    //             ->selectRaw('
    //                 COALESCE(reason_lost, "Non spécifié") as reason,
    //                 COUNT(*) as count,
    //                 SUM(valeur_potentielle) as lost_value
    //             ')
    //             ->groupBy('reason_lost')
    //             ->orderByDesc('count')
    //             ->get()
    //             ->map(function($item) {
    //                 return [
    //                     'name' => $item->reason,
    //                     'value' => $item->count,
    //                     'lost_value' => round($item->lost_value, 2)
    //                 ];
    //             });

    //         return response()->json([
    //             'success' => true,
    //             'data' => $data,
    //             'chart_type' => 'pie'
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    //     }
    // }


    /**
     * Score de qualité des prospects par source
     */
    // public function chartLeadQualityBySource()
    // {
    //     try {
    //         // Supposons une colonne 'source' dans prospects
    //         $data = Prospect::selectRaw('
    //                 COALESCE(source, "Direct") as source,
    //                 COUNT(*) as total,
    //                 SUM(CASE WHEN statut = "converti" THEN 1 ELSE 0 END) as converted,
    //                 AVG(valeur_potentielle) as avg_value,
    //                 AVG(DATEDIFF(COALESCE(converted_at, NOW()), created_at)) as avg_conversion_days
    //             ')
    //             ->groupBy('source')
    //             ->having('total', '>', 5) // Minimum 5 prospects pour être significatif
    //             ->get()
    //             ->map(function($item) {
    //                 $conversionRate = $item->total > 0 ? round(($item->converted / $item->total) * 100, 1) : 0;

    //                 // Score de qualité basé sur taux de conversion et valeur moyenne
    //                 $qualityScore = ($conversionRate * 0.7) + (min($item->avg_value / 10000, 10) * 3);

    //                 return [
    //                     'name' => $item->source,
    //                     'total' => $item->total,
    //                     'conversion_rate' => $conversionRate,
    //                     'avg_value' => round($item->avg_value, 2),
    //                     'avg_conversion_days' => round($item->avg_conversion_days, 1),
    //                     'quality_score' => round($qualityScore, 1)
    //                 ];
    //             })
    //             ->sortByDesc('quality_score')
    //             ->values();

    //         return response()->json([
    //             'success' => true,
    //             'data' => $data,
    //             'chart_type' => 'scatter'
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    //     }
    // }

    /**
     * Analyse de la durée de vie des prospects (cohort analysis)
     */
    // public function chartCohortAnalysis()
    // {
    //     try {
    //         $cohorts = Prospect::selectRaw('
    //                 DATE_FORMAT(created_at, "%Y-%m") as cohort_month,
    //                 COUNT(*) as initial_count
    //             ')
    //             ->where('created_at', '>=', now()->subMonths(12))
    //             ->groupBy('cohort_month')
    //             ->get();

    //         $cohortData = $cohorts->map(function($cohort) {
    //             $cohortProspects = Prospect::whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$cohort->cohort_month]);

    //             $periods = [];
    //             for ($period = 0; $period <= 6; $period++) {
    //                 $endDate = \Carbon\Carbon::createFromFormat('Y-m', $cohort->cohort_month)
    //                                        ->addMonths($period)
    //                                        ->endOfMonth();

    //                 $activeCount = $cohortProspects->where('statut', '!=', 'perdu')
    //                                               ->where(function($q) use ($endDate) {
    //                                                   $q->whereNull('converted_at')
    //                                                     ->orWhere('converted_at', '>', $endDate);
    //                                               })
    //                                               ->count();

    //                 $retentionRate = $cohort->initial_count > 0 ? 
    //                     round(($activeCount / $cohort->initial_count) * 100, 1) : 0;

    //                 $periods["month_$period"] = $retentionRate;
    //             }

    //             return array_merge([
    //                 'cohort' => $cohort->cohort_month,
    //                 'initial_count' => $cohort->initial_count
    //             ], $periods);
    //         });

    //         return response()->json([
    //             'success' => true,
    //             'data' => $cohortData,
    //             'chart_type' => 'heatmap'
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    //     }
    // }

}
