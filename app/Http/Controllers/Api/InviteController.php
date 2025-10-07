<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Models\Entreprise;
use App\Models\Task;
use App\Models\Prospect;
use App\Http\Requests\InviteRequest;
use App\Exceptions\SuivieProjet\InviteExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvitationMail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\InvitePipelineStage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\ProspectPipelineStage;
use App\Models\ProspectPipelineProgression; // Ajouter aussi celle-ci
use App\Services\PipelineTaskService; // Import the PipelineTaskService
use Carbon\Carbon;





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
        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->has('potentiel')) {
            $query->where('potentiel', $request->potentiel);
        }

        if ($request->has('action_id')) {
            $query->where('action_id', $request->action_id);
        }

        // Tri et pagination
        $sortField = $request->sort_by ?? 'created_at';
        $sortDirection = $request->sort_direction ?? 'desc';
        $invites = $query->orderBy($sortField, $sortDirection)
            ->paginate($request->per_page ?? 15);

        $invites->getCollection()->transform(function ($invite) {
            return $invite->append('is_converted');
        });

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
                $invite->append('is_converted');

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

            // Initialiser le pipeline automatiquement
            $invite->initializePipeline(auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Invité créé avec succès',
                'data' => $invite->load(['pipelineStage', 'pipelineProgressions.stage'])
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
            $invite = Invite::with(['pipelineStage', 'pipelineProgressions.stage','prospect'])->findOrFail($id);
            $invite->append('is_converted');
            // Obtenir toutes les étapes du pipeline depuis la base de données
            $allStages = InvitePipelineStage::getAllStagesInOrder();

            return response()->json([
                'success' => true,
                'data' => [
                    'invite' => $invite,
                    'current_stage' => $invite->pipelineStage,
                    'all_stages' => $allStages,
                    'progressions' => $invite->pipelineProgressions,
                    'progression_percentage' => $invite->progressionPercentage(),
                    'can_convert' => $invite->canConvertToProspect(),
                    'pipeline_details' => $invite->getPipelineStatusDetails()
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

            // Vérifier d'abord qu'il y a des étapes dans la base de données
            $stagesCount = InvitePipelineStage::where('is_active', true)->count();
            if ($stagesCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune étape de pipeline configurée dans la base de données. Veuillez créer les étapes d\'abord.'
                ], 400);
            }

            $userId = $request->user_id ?? auth()->id();

            if ($invite->initializePipeline($userId)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pipeline initialisé avec succès',
                    'data' => $invite->fresh(['pipelineStage', 'pipelineProgressions.stage'])
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => "Impossible d'initialiser le pipeline. Vérifiez les étapes dans la base de données."
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initialisation du pipeline',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Avancer à l'étape suivante du pipeline
     */
   /**
 * Avancer à l'étape suivante du pipeline
 */
public function advanceStage(Request $request, $id)
{
    try {
        // Validation de la requête simplifiée (uniquement notes)
        $validator = Validator::make($request->all(), [
            'notes' => 'nullable|string',
        ]);
 
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $validator->errors()
            ], 422);
        }

        $invite = Invite::with(['pipelineStage'])->findOrFail($id);
        $currentStage = $invite->currentStage();
        
        if (!$currentStage) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune étape actuelle trouvée.'
            ], 400);
        }

        // Avancer à l'étape suivante (si possible)
        if (!$currentStage->is_final) {
            if (!$invite->advanceToNextStage(Auth::id(), $request->input('notes'))) {
                return response()->json([
                    'success' => false,
                    'message' => "Échec de l'avancement dans le pipeline"
                ], 500);
            }
            
            $invite->refresh();
        } else {
            // Traiter l'étape finale (marquer comme complétée)
            $currentProgression = $invite->pipelineProgressions()
                ->where('stage_id', $currentStage->id)
                ->where('completed', false)
                ->first();
                
            if ($currentProgression) {
                $currentProgression->update([
                    'completed' => true,
                    'completed_at' => now(),
                    'notes' => $request->input('notes')
                ]);
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => $currentStage->is_final ? 'Étape finale complétée' : 'Avancé à l\'étape suivante',
            'data' => [
                'invite' => $invite->load(['pipelineStage']),
                'is_final_stage' => $invite->currentStage()?->is_final ?? false
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error("Exception advanceStage: " . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'message' => "Erreur lors de l'avancement dans le pipeline",
            'error' => $e->getMessage()
        ], 500);
    }
}

   
    /**
 * Créer une tâche pour une étape spécifique du pipeline
 */
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



    /**
     * Récupérer les tâches associées à une étape spécifique du pipeline
     */
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

    /**
     * Récupérer les tâches associées à une étape d'un invité (alternative)
     */
    public function getStageTasks($inviteId, $stageId)
    {
        try {
            $invite = Invite::findOrFail($inviteId);
            $stage = InvitePipelineStage::findOrFail($stageId);
            
            $tasks = Task::where('entity_type', 'invite')
                ->where('entity_id', $invite->id)
                ->where('pipeline_stage_id', $stageId)
                ->with(['user:id,name', 'assignee:id,name'])
                ->orderBy('created_at', 'desc')
                ->get();
                
            return response()->json([
                'success' => true,
                'data' => [
                    'invite' => $invite->only(['id', 'nom', 'prenom', 'email']),
                    'stage' => $stage->only(['id', 'name', 'order', 'color']),
                    'tasks' => $tasks
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des tâches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les tâches pour une étape et un invité spécifiques
     */
    private function getTasksForStage(Invite $invite, ?InvitePipelineStage $stage)
    {
        if (!$stage) {
            return [];
        }

        try {
            $tasks = Task::where('entity_type', 'invite')
                ->where('entity_id', $invite->id)
                ->where('pipeline_stage_id', $stage->id)
                ->with(['user:id,name', 'assignee:id,name'])
                ->orderBy('created_at', 'desc')
                ->get();

            return $tasks;
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la récupération des tâches pour l\'étape:', [
                'invite_id' => $invite->id,
                'stage_id' => $stage->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

   /**
 * Créer une tâche pour une étape spécifique
 */
private function createTaskForStage(Invite $invite, ?InvitePipelineStage $stage, Request $request)
{
    if (!$stage) {
        \Log::warning("Tentative de création de tâche pour une étape null");
        throw new \Exception("L'étape du pipeline n'existe pas");
    }

    try {
        // Debug détaillé
        \Log::info('Tentative de création de tâche', [
            'invite_id' => $invite->id,
            'stage_id' => $stage->id,
            'title' => $request->input('task_title'),
            'all_data' => $request->all()
        ]);

        // Validation des champs obligatoires
        if (!$request->input('task_title')) {
            throw new \Exception("Le titre de la tâche est obligatoire");
        }
        
        if (!$request->input('task_start')) {
            throw new \Exception("La date de début de la tâche est obligatoire");
        }

        // Création de la tâche avec validation des valeurs
        $task = new Task();
        $task->title = $request->input('task_title');
        $task->description = $request->input('task_description') ?? '';
        $task->start = $request->input('task_start');
        
        if ($request->input('task_end')) {
            $task->end = $request->input('task_end');
        }
        
        $task->type = $request->input('task_type', 'todo');
        $task->status = 'not_started';
        $task->priority = $request->input('task_priority', 'medium');
        $task->color = $this->getTaskColorByType($request->input('task_type', 'todo'));
        $task->user_id = Auth::id();
        $task->assignee_id = $invite->proprietaire_id ?? Auth::id();
        $task->entity_type = 'invite';
        $task->entity_id = $invite->id;
        $task->pipeline_stage_id = $stage->id;
        
        // Sauvegarde avec exception si échec
        $task->saveOrFail();

        \Log::info('Tâche créée avec succès', [
            'task_id' => $task->id,
            'invite_id' => $invite->id,
            'stage_id' => $stage->id
        ]);

        return $task->fresh(['user:id,name', 'assignee:id,name']);
    } catch (\Exception $e) {
        // Log détaillé de l'erreur
        \Log::error('Erreur création tâche:', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'invite_id' => $invite->id,
            'stage_id' => $stage->id
        ]);
        
        // IMPORTANT: Propager l'exception au lieu de retourner null
        throw $e;
    }
}

    /**
     * Obtenir une couleur basée sur le type de tâche
     */
    private function getTaskColorByType(?string $type): string
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
     * Convertir l'invité en prospect
     */

     
     public function convertToProspect(Request $request, $id)
     {
         try {
             $invite = Invite::with(['pipelineStage'])->findOrFail($id);
             $userId = auth()->id();
             
             if ($invite->isConvertedToProspect()) {
                 return response()->json([
                     'success' => true,
                     'message' => 'Cet invité a déjà été converti en prospect',
                     'data' => [
                         'invite' => $invite->append('is_converted'),
                         'prospect' => $invite->prospect->load(['pipelineStage'])
                     ]
                 ]);
             }
     
             // ✅ VÉRIFICATION SIMPLIFIÉE : juste vérifier si on peut convertir
             if (!$invite->canConvertToProspect()) {
                 return response()->json([
                     'success' => false,
                     'message' => 'L\'invité doit être dans l\'étape finale pour être converti en prospect.'
                 ], 400);
             }
     
             // Utiliser une transaction pour garantir l'intégrité des données
             DB::beginTransaction();
             
             try {
                 // ✅ NOUVELLE LOGIQUE : Marquer l'étape finale comme complétée AVANT la conversion
                 $currentStage = $invite->pipelineStage;
                 if ($currentStage && $currentStage->is_final) {
                     $finalProgression = $invite->pipelineProgressions()
                         ->where('stage_id', $currentStage->id)
                         ->first();
     
                     if ($finalProgression && !$finalProgression->completed) {
                         $finalProgression->update([
                             'completed' => true,
                             'completed_at' => now(),
                             'notes' => ($finalProgression->notes ?? '') . ' - Complétée automatiquement lors de la conversion en prospect'
                         ]);
                     } elseif (!$finalProgression) {
                         // Créer la progression finale si elle n'existe pas
                         $invite->pipelineProgressions()->create([
                             'stage_id' => $currentStage->id,
                             'completed' => true,
                             'completed_at' => now(),
                             'assigned_to' => $userId,
                             'notes' => 'Étape finale complétée automatiquement lors de la conversion'
                         ]);
                     }
                 }
     
                 // Données envoyées par le front
                 $data = $request->only([
                     'entreprise_id',
                     'nom',
                     'email',
                     'telephone',
                     'adresse',
                     'pays_id',
                     'secteur_id',
                     'statut',
                     'description',
                     'notes_internes',
                     'valeur_potentielle',
                     'devise',
                     'date_dernier_contact',
                     'prochain_contact_prevu'
                 ]);
                 
                 // Créer le prospect
                 $prospect = Prospect::create(array_merge($data, [
                     'invite_id'       => $invite->id,
                     'responsable_id'  => $userId,
                     'created_by'      => $userId,
                 ]));
                 
                 // Marquer l'invité comme converti
                 $invite->update([
                     'date_conversion' => now(), 
                     'is_converted' => true,
                     'pipeline_completed_at' => now(),  // ✅ NOUVEAU : Marquer le pipeline comme terminé
                     'pipeline_completed_by' => $userId
                 ]);
                 
                 // Initialiser le pipeline du prospect
                 $firstStage = ProspectPipelineStage::where('is_active', true)
                     ->orderBy('order')
                     ->first();
                     
                 if ($firstStage) {
                     // Définir l'étape initiale
                     $prospect->update(['pipeline_stage_id' => $firstStage->id]);
                     
                     // Créer la première progression
                     ProspectPipelineProgression::create([
                         'prospect_id' => $prospect->id,
                         'stage_id' => $firstStage->id,
                         'completed' => false,
                         'assigned_to' => $userId
                     ]);
                 }
                 
                 DB::commit();
                 
                 // Recharger les données avec les relations
                 $invite->refresh()->append('is_converted');
                 $prospect->load(['pipelineStage', 'pipelineProgressions.stage']);
                 
                 return response()->json([
                     'success' => true,
                     'message' => 'Invité converti en prospect avec succès',
                     'data' => [
                         'invite' => $invite,
                         'prospect' => $prospect,
                         'conversion_info' => [
                             'pipeline_completed' => true,
                             'final_stage_completed' => true,
                             'progression_percentage' => $invite->progressionPercentage(),
                             'prospect_pipeline_initialized' => !is_null($firstStage)
                         ]
                     ]
                 ]);
                 
             } catch (\Exception $e) {
                 DB::rollBack();
                 throw $e;
             }
         } catch (\Exception $e) {
             \Log::error('Erreur conversion invité', [
                 'invite_id' => $id,
                 'payload'   => $request->all(),
                 'error'     => $e->getMessage(),
                 'trace'     => $e->getTraceAsString()
             ]);
             
             return response()->json([
                 'success' => false,
                 'message' => "Impossible de convertir l'invité en prospect: " . $e->getMessage()
             ], 500);
         }
     }
    
/**
 * Récupérer les données de progression pour un invité
 * 
 * @param int $id ID de l'invité
 * @return \Illuminate\Http\JsonResponse
 */
public function getProgression($id)
{
    try {
        $invite = Invite::with(['pipelineStage', 'pipelineProgressions.stage', 'pipelineProgressions.user'])
            ->findOrFail($id);

        // Récupérer toutes les étapes du pipeline
        $allStages = InvitePipelineStage::getAllStagesInOrder();
        
        // Calculer les étapes complétées
        $completedStages = $invite->pipelineProgressions()
            ->where('completed', true)
            ->count();
            
        // Calculer le pourcentage de progression
        $totalStages = $allStages->count();
        $progressionPercentage = $totalStages > 0 
            ? round(($completedStages / $totalStages) * 100) 
            : 0;
            
        // Récupérer l'étape actuelle et calculer son index
        $currentStage = $invite->pipelineStage;
        $currentStageIndex = $currentStage 
            ? $allStages->search(function($item) use ($currentStage) {
                return $item->id === $currentStage->id;
            })
            : -1;
            
        // Étape suivante (si disponible)
        $nextStage = null;
        if ($currentStageIndex !== -1 && $currentStageIndex < ($totalStages - 1)) {
            $nextStage = $allStages[$currentStageIndex + 1];
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'invite' => $invite->only(['id', 'nom', 'prenom', 'email', 'statut']),
                'current_stage' => $currentStage,
                'next_stage' => $nextStage,
                'progression_percentage' => $progressionPercentage,
                'completed_stages' => $completedStages,
                'total_stages' => $totalStages,
                'all_stages' => $allStages,
                'progressions' => $invite->pipelineProgressions->map(function($progression) {
                    return [
                        'id' => $progression->id,
                        'stage_id' => $progression->stage_id,
                        'stage_name' => $progression->stage->name,
                        'completed' => $progression->completed,
                        'completed_at' => $progression->completed_at,
                        'notes' => $progression->notes,
                        'user' => $progression->user ? [
                            'id' => $progression->user->id,
                            'name' => $progression->user->name
                        ] : null,
                    ];
                }),
                'is_converted' => $invite->is_converted,
                'can_convert' => $invite->canConvertToProspect()
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => "Erreur lors de la récupération de la progression: " . $e->getMessage()
        ], 500);
    }
}
protected function getEntityType()
{
    return 'invite';
}



public function invitesByCountry()
{
    try {
        \Log::info('Début de la méthode invitesByCountry');

        // Vérifier si des invités existent
        $inviteCount = DB::table('invites')->count();
        \Log::info('Nombre total d\'invités : ' . $inviteCount);

        if ($inviteCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun invité trouvé dans la base de données'
            ], 404);
        }

        // Récupérer les données des invités groupées par pays
        $data = DB::table('invites')
            ->join('pays', 'invites.pays_id', '=', 'pays.id')
            ->select('pays.name_pays as country', DB::raw('COUNT(invites.id) as count'))
            ->groupBy('pays.name_pays')
            ->orderByDesc('count')
            ->get();

        \Log::info('Données récupérées : ', $data->toArray());

        // Vérifier si des données existent après la jointure
        if ($data->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune donnée trouvée pour les invités par pays'
            ], 404);
        }

        // Transformer les données pour ECharts
        $formattedData = $data->map(function ($item) {
            return [
                'name' => $item->country,
                'value' => $item->count
            ];
        });

        \Log::info('Données formatées pour ECharts : ', $formattedData->toArray());

        return response()->json([
            'success' => true,
            'data' => $formattedData
        ]);
    } catch (\Exception $e) {
        \Log::error('Erreur dans invitesByCountry : ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des données des invités par pays',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function stats()
{
    try {
        $total = Invite::count();
        $aujourd_hui = Invite::whereDate('created_at', today())->count();
        $cette_semaine = Invite::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $ce_mois = Invite::whereMonth('created_at', now()->month)->count();
        
        // Taux de conversion
        $convertis = Invite::where('is_converted', true)->count();
        $taux_conversion = $total > 0 ? round(($convertis / $total) * 100, 2) : 0;
        
        // Invités actifs (dans le pipeline)
        $en_pipeline = Invite::whereNotNull('pipeline_stage_id')
                            ->where('is_converted', false)
                            ->count();
        
        // Invités nécessitant un suivi
        $suivi_requis = Invite::where('suivi_requis', true)
                             ->where('is_converted', false)
                             ->count();
        
        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'aujourd_hui' => $aujourd_hui,
                'cette_semaine' => $cette_semaine,
                'ce_mois' => $ce_mois,
                'convertis' => $convertis,
                'taux_conversion' => $taux_conversion,
                'en_pipeline' => $en_pipeline,
                'suivi_requis' => $suivi_requis,
                'moyenne_par_jour' => $ce_mois > 0 ? round($ce_mois / now()->day, 1) : 0
            ]
        ]);
    } catch (\Exception $e) {
        return InviteExceptionHandler::handle($e);
    }
}

/**
 * Répartition des invités par statut (Pie Chart)
 */
public function chartByStatus()
{
    try {
        $data = Invite::select('statut', DB::raw('COUNT(*) as count'))
                     ->groupBy('statut')
                     ->orderByDesc('count')
                     ->get()
                     ->map(function ($item) {
                         return [
                             'name' => $this->getStatusLabel($item->statut),
                             'value' => $item->count,
                             'code' => $item->statut
                         ];
                     });

        return response()->json([
            'success' => true,
            'data' => $data,
            'chart_type' => 'pie'
        ]);
    } catch (\Exception $e) {
        return InviteExceptionHandler::handle($e);
    }
}

/**
 * Répartition des invités par potentiel (Donut Chart)
 */
public function chartByPotentiel()
{
    try {
        $data = Invite::select('potentiel', DB::raw('COUNT(*) as count'))
                     ->groupBy('potentiel')
                     ->orderByDesc('count')
                     ->get()
                     ->map(function ($item) {
                         return [
                             'name' => ucfirst($item->potentiel ?: 'Non défini'),
                             'value' => $item->count,
                             'code' => $item->potentiel
                         ];
                     });

        return response()->json([
            'success' => true,
            'data' => $data,
            'chart_type' => 'donut'
        ]);
    } catch (\Exception $e) {
        return InviteExceptionHandler::handle($e);
    }
}

/**
 * Évolution des invités par mois (Line Chart)
 */
public function chartEvolutionMensuelle()
{
    try {
        $data = Invite::select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as count')
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
                        'value' => $item->count,
                        'date' => $date->format('Y-m')
                    ];
                });

        return response()->json([
            'success' => true,
            'data' => $data,
            'chart_type' => 'line'
        ]);
    } catch (\Exception $e) {
        return InviteExceptionHandler::handle($e);
    }
}

/**
 * Répartition des invités par pays (Bar Chart)
 */
public function chartByPays()
{
    try {
        $data = Invite::join('pays', 'invites.pays_id', '=', 'pays.id')
                     ->select('pays.name_pays as country', DB::raw('COUNT(invites.id) as count'))
                     ->groupBy('pays.name_pays')
                     ->orderByDesc('count')
                     ->limit(10) // Top 10 pays
                     ->get()
                     ->map(function ($item) {
                         return [
                             'name' => $item->country,
                             'value' => $item->count
                         ];
                     });

        return response()->json([
            'success' => true,
            'data' => $data,
            'chart_type' => 'bar'
        ]);
    } catch (\Exception $e) {
        return InviteExceptionHandler::handle($e);
    }
}

/**
 * Répartition des invités par secteur (Horizontal Bar Chart)
 */
public function chartBySecteur()
{
    try {
        $data = Invite::join('secteurs', 'invites.secteur_id', '=', 'secteurs.id')
                     ->select('secteurs.name as secteur', DB::raw('COUNT(invites.id) as count'))
                     ->groupBy('secteurs.name')
                     ->orderByDesc('count')
                     ->limit(8) // Top 8 secteurs
                     ->get()
                     ->map(function ($item) {
                         return [
                             'name' => $item->secteur,
                             'value' => $item->count
                         ];
                     });

        return response()->json([
            'success' => true,
            'data' => $data,
            'chart_type' => 'horizontal_bar'
        ]);
    } catch (\Exception $e) {
        return InviteExceptionHandler::handle($e);
    }
}

/**
 * Progression dans le pipeline (Funnel Chart)
 */
public function chartPipelineProgression()
{
    try {
        $data = InvitePipelineStage::leftJoin('invites', 'invite_pipeline_stages.id', '=', 'invites.pipeline_stage_id')
                                  ->select(
                                      'invite_pipeline_stages.name as stage_name',
                                      'invite_pipeline_stages.order',
                                      DB::raw('COUNT(invites.id) as count')
                                  )
                                  ->where('invite_pipeline_stages.is_active', true)
                                  ->groupBy('invite_pipeline_stages.id', 'invite_pipeline_stages.name', 'invite_pipeline_stages.order')
                                  ->orderBy('invite_pipeline_stages.order')
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
        return InviteExceptionHandler::handle($e);
    }
}

/**
 * Taux de conversion par mois (Line + Bar Chart)
 */
public function chartConversionRate()
{
    try {
        $data = Invite::select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN is_converted = 1 THEN 1 ELSE 0 END) as convertis')
                )
                ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    $date = Carbon::createFromDate($item->year, $item->month, 1);
                    $taux = $item->total > 0 ? round(($item->convertis / $item->total) * 100, 1) : 0;
                    
                    return [
                        'name' => $date->format('M Y'),
                        'total' => $item->total,
                        'convertis' => $item->convertis,
                        'taux' => $taux,
                        'date' => $date->format('Y-m')
                    ];
                });

        return response()->json([
            'success' => true,
            'data' => $data,
            'chart_type' => 'combination'
        ]);
    } catch (\Exception $e) {
        return InviteExceptionHandler::handle($e);
    }
}

/**
 * Répartition par type d'invité (Pie Chart)
 */
public function chartByType()
{
    try {
        $data = Invite::select('type_invite', DB::raw('COUNT(*) as count'))
                     ->groupBy('type_invite')
                     ->get()
                     ->map(function ($item) {
                         return [
                             'name' => $item->type_invite === 'interne' ? 'Interne' : 'Externe',
                             'value' => $item->count,
                             'code' => $item->type_invite
                         ];
                     });

        return response()->json([
            'success' => true,
            'data' => $data,
            'chart_type' => 'pie'
        ]);
    } catch (\Exception $e) {
        return InviteExceptionHandler::handle($e);
    }
}

/**
 * Top 10 des entreprises avec le plus d'invités (Bar Chart)
 */
public function chartTopEntreprises()
{
    try {
        $data = Invite::join('entreprises', 'invites.entreprise_id', '=', 'entreprises.id')
                     ->select('entreprises.nom as entreprise', DB::raw('COUNT(invites.id) as count'))
                     ->groupBy('entreprises.nom')
                     ->orderByDesc('count')
                     ->limit(10)
                     ->get()
                     ->map(function ($item) {
                         return [
                             'name' => $item->entreprise,
                             'value' => $item->count
                         ];
                     });

        return response()->json([
            'success' => true,
            'data' => $data,
            'chart_type' => 'bar'
        ]);
    } catch (\Exception $e) {
        return InviteExceptionHandler::handle($e);
    }
}

/**
 * Heatmap des invitations par jour de la semaine et heure
 */
public function chartHeatmapCreation()
{
    try {
        $data = Invite::select(
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
        return InviteExceptionHandler::handle($e);
    }
}

/**
 * Dashboard complet avec tous les graphiques
 */
public function dashboard()
{
    try {
        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $this->stats()->getData()->data,
                'charts' => [
                    'status' => $this->chartByStatus()->getData()->data,
                    'potentiel' => $this->chartByPotentiel()->getData()->data,
                    'evolution' => $this->chartEvolutionMensuelle()->getData()->data,
                    'pays' => $this->chartByPays()->getData()->data,
                    'secteur' => $this->chartBySecteur()->getData()->data,
                    'pipeline' => $this->chartPipelineProgression()->getData()->data,
                    'conversion' => $this->chartConversionRate()->getData()->data,
                    'type' => $this->chartByType()->getData()->data,
                    'entreprises' => $this->chartTopEntreprises()->getData()->data
                ]
            ]
        ]);
    } catch (\Exception $e) {
        return InviteExceptionHandler::handle($e);
    }
}

/**
 * Méthode helper pour les labels de statut
 */
private function getStatusLabel($status)
{
    $labels = [
        'en_attente' => 'En attente',
        'envoyee' => 'Envoyée',
        'confirmee' => 'Confirmée',
        'refusee' => 'Refusée',
        'details_envoyes' => 'Détails envoyés',
        'participation_confirmee' => 'Participation confirmée',
        'participation_sans_suivi' => 'Participation sans suivi',
        'absente' => 'Absente',
        'aucune_reponse' => 'Aucune réponse'
    ];

    return $labels[$status] ?? ucfirst($status);
}
    

}