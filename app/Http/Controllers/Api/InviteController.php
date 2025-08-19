<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Models\Entreprise;
use App\Http\Requests\InviteRequest;
use App\Exceptions\SuivieProjet\InviteExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvitationMail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\InvitePipelineStage;


class InviteController extends Controller
{
    /**
     * Liste des invités avec filtres possibles
     */
    public function index(Request $request)
    {
        try {
            $query = Invite::query()->with(['entreprise', 'action', 'etape', 'proprietaire']);
            
            // Filtres
            if ($request->has('entreprise_id')) {
                $query->where('entreprise_id', $request->entreprise_id);
            }
            
            if ($request->has('statut')) {
                $query->where('statut', $request->statut);
            }
            
            if ($request->has('type_invite')) {
                $query->where('type_invite', $request->type_invite);
            }
            
            if ($request->has('date_debut') && $request->has('date_fin')) {
                $query->whereBetween('date_evenement', [$request->date_debut, $request->date_fin]);
            }
            
            // Tri et pagination
            $sortField = $request->sort_by ?? 'created_at';
            $sortDirection = $request->sort_direction ?? 'desc';
            $invites = $query->orderBy($sortField, $sortDirection)
                            ->paginate($request->per_page ?? 15);
            
            return response()->json([
                'success' => true,
                'data' => $invites
            ]);
            
        } catch (\Exception $e) {
            return InviteExceptionHandler::handle($e);
        }
    }

    /**
     * Afficher un invité spécifique
     */
    public function show($id)
    {
        try {
            $invite = Invite::with(['entreprise', 'action', 'etape', 'proprietaire'])
                          ->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $invite
            ]);
            
        } catch (\Exception $e) {
            return InviteExceptionHandler::handle($e);
        }
    }

    /**
     * Créer un nouvel invité
     */
    public function store(InviteRequest $request)
    {
        try {
            $invite = Invite::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Invité créé avec succès',
                'data' => $invite
            ], 201);
            
        } catch (\Exception $e) {
            return InviteExceptionHandler::handle($e);
        }
    }

    /**
     * Mettre à jour un invité
     */
    public function update(InviteRequest $request, $id)
    {
        try {
            $invite = Invite::findOrFail($id);
            $invite->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Invité mis à jour avec succès',
                'data' => $invite
            ]);
            
        } catch (\Exception $e) {
            return InviteExceptionHandler::handle($e);
        }
    }

    /**
     * Mettre à jour le statut d'un invité
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $invite = Invite::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'statut' => 'required|in:en_attente,envoyee,confirmee,refusee,details_envoyes,participation_confirmee,participation_sans_suivi,absente,aucune_reponse'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $invite->statut = $request->statut;
            $invite->save();

            return response()->json([
                'success' => true,
                'message' => 'Statut de l\'invité mis à jour',
                'data' => $invite
            ]);
            
        } catch (\Exception $e) {
            return InviteExceptionHandler::handle($e);
        }
    }

    /**
     * Supprimer un invité
     */
    public function destroy($id)
    {
        try {
            $invite = Invite::findOrFail($id);
            $invite->delete();

            return response()->json([
                'success' => true,
                'message' => 'Invité supprimé avec succès'
            ]);
            
        } catch (\Exception $e) {
            return InviteExceptionHandler::handle($e);
        }
    }

    /**
     * Liste des invités par entreprise
     */
    public function getByEntreprise($entrepriseId)
    {
        try {
            $entreprise = Entreprise::findOrFail($entrepriseId);
            
            $invites = $entreprise->invites()
                                ->with(['action', 'etape', 'proprietaire'])
                                ->orderBy('created_at', 'desc')
                                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $invites
            ]);
            
        } catch (\Exception $e) {
            return InviteExceptionHandler::handle($e);
        }
    }

   

/**
 * Envoyer l'invitation par email
 */
public function sendInvitation($id)
{
    try {
        // Charger l'invitation avec sa relation action
        $invite = Invite::with('action')->findOrFail($id);
        
        if ($invite->sendInvitation()) {
            return response()->json([
                'success' => true,
                'message' => 'Invitation envoyée avec succès',
                'data' => $invite->fresh()
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => "Échec de l'envoi de l'invitation"
        ], 500);
        
    } catch (\Exception $e) {
        // Ajoutez ce log pour voir l'erreur exacte
        \Log::error('Erreur dans sendInvitation: ' . $e->getMessage());
        return InviteExceptionHandler::handle($e);
    }
}

/**
 * Afficher l'état actuel du pipeline pour un invité
 */
public function getPipelineStatus($id)
{
    try {
        $invite = Invite::with(['pipelineType', 'pipelineStage', 'pipelineProgressions.stage'])
                        ->findOrFail($id);
        
        if (!$invite->pipeline_type_id || !$invite->pipeline_stage_id) {
            return response()->json([
                'success' => false,
                'message' => 'Pipeline non initialisé pour cet invité',
                'data' => $invite
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'invite' => $invite,
                'pipeline' => [
                    'type' => $invite->pipelineType,
                    'current_stage' => $invite->pipelineStage,
                    'progressions' => $invite->pipelineProgressions,
                    'next_stages' => $this->getNextStages($invite)
                ]
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error('Erreur dans getPipelineStatus: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => "Erreur lors de la récupération de l'état du pipeline",
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Obtenir les étapes suivantes possibles
 */
private function getNextStages($invite)
{
    if (!$invite->pipelineStage) return [];
    
    return InvitePipelineStage::where('pipeline_type_id', $invite->pipeline_type_id)
                             ->where('order', '>', $invite->pipelineStage->order)
                             ->orderBy('order')
                             ->get();
}

/**
 * Gérer la confirmation d'une invitation via le token
 */
public function confirm($token)
    {
        try {
            $invite = Invite::where('token', $token)->firstOrFail();
            
            if ($invite->isConfirmee() || $invite->isRefusee()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette invitation a déjà reçu une réponse',
                    'data' => [
                        'invite' => $invite,
                        'statut' => $invite->statut
                    ]
                ], 422);
            }
            
            $invite->markAsConfirmed();
            
            return response()->json([
                'success' => true,
                'message' => 'Participation confirmée avec succès',
                'data' => [
                    'invite' => $invite,
                    'action' => $invite->action
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation non trouvée ou déjà traitée'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la confirmation: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "Une erreur s'est produite lors de la confirmation"
            ], 500);
        }
    }

/**
 * Gérer le refus d'une invitation via le token
 */
public function decline($token)
    {
        try {
            $invite = Invite::where('token', $token)->firstOrFail();
            
            if ($invite->isConfirmee() || $invite->isRefusee()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette invitation a déjà reçu une réponse',
                    'data' => [
                        'invite' => $invite,
                        'statut' => $invite->statut
                    ]
                ], 422);
            }
            
            $invite->markAsDeclined();
            
            return response()->json([
                'success' => true,
                'message' => 'Participation refusée avec succès',
                'data' => [
                    'invite' => $invite,
                    'action' => $invite->action
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation non trouvée ou déjà traitée'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Erreur lors du refus: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => "Une erreur s'est produite lors du refus"
            ], 500);
        }
    }

/**
 * Gérer le pipeline de l'invité
 */
public function initializePipeline(Request $request, $id)
{
    try {
        $invite = Invite::findOrFail($id);
        $userId = $request->user_id ?? auth()->id();
        
        if ($invite->initializePipeline($userId)) {
            return response()->json([
                'success' => true,
                'message' => 'Pipeline initialisé avec succès',
                'data' => $invite->fresh(['pipelineType', 'pipelineStage'])
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => "Impossible d'initialiser le pipeline"
        ], 500);
        
    } catch (\Exception $e) {
        return InviteExceptionHandler::handle($e);
    }
}

/**
 * Avancer l'invité à l'étape suivante du pipeline
 */
public function advanceStage(Request $request, $id)
{
    try {
        $invite = Invite::findOrFail($id);
        $userId = $request->user_id ?? auth()->id();
        $notes = $request->notes;
        
        if ($invite->advanceToNextStage($userId, $notes)) {
            return response()->json([
                'success' => true,
                'message' => 'Invité avancé à l\'étape suivante',
                'data' => $invite->fresh(['pipelineStage'])
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => "Impossible d'avancer à l'étape suivante"
        ], 500);
        
    } catch (\Exception $e) {
        return InviteExceptionHandler::handle($e);
    }
}

/**
 * Convertir l'invité en prospect
 */
public function convertToProspect(Request $request, $id)
{
    try {
        $invite = Invite::findOrFail($id);
        $userId = $request->user_id ?? auth()->id();
        
        $prospect = $invite->convertToProspect($userId);
        
        if ($prospect) {
            return response()->json([
                'success' => true,
                'message' => 'Invité converti en prospect avec succès',
                'data' => [
                    'invite' => $invite,
                    'prospect' => $prospect
                ]
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => "Impossible de convertir l'invité en prospect"
        ], 500);
        
    } catch (\Exception $e) {
        return InviteExceptionHandler::handle($e);
    }
}
}