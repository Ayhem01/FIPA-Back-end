<?php
namespace App\Http\Controllers\Api;

use App\Models\Blockage;
use App\Services\BlockageService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class BlockageController extends Controller
{
    protected $service;

    public function __construct(BlockageService $service)
    {
        $this->service = $service;
    }


    public function index(Request $request)
    {
        $request->validate([
            'blockable_type' => 'required|string|in:invite,prospect,investisseur,projet',
            'blockable_id'   => 'required|integer',
        ]);
    
        $blockages = Blockage::with(['assignedUser', 'createdByUser', 'resolvedBy'])
            ->where('blockable_type', $request->blockable_type)
            ->where('blockable_id', $request->blockable_id)
            ->orderBy('created_at', 'desc')
            ->get();
    
        return response()->json([
            'success' => true,
            'data' => $blockages
        ]);
    }
    public function indexadmin(Request $request)
    {
        try {
            // ✅ Validation des paramètres
            $request->validate([
                'status' => 'nullable|string|in:actif,resolu,annule,open,resolved,cancelled,in_progress',
                'priority' => 'nullable|string|in:low,medium,high,critical',
                'blockage_type' => 'nullable|string|in:process,data,technical,other',
                'blockable_type' => 'nullable|string|in:invite,prospect,investisseur,projet',
                'is_escalated' => 'nullable|boolean',
                'assigned_to' => 'nullable|exists:users,id',
                'created_by' => 'nullable|exists:users,id',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date',
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:created_at,updated_at,priority,status,name',
                'sort_direction' => 'nullable|string|in:asc,desc'
            ]);
    
            // ✅ Construire la requête de base
            $query = Blockage::with([
                'assignedUser:id,name,email',
                'createdByUser:id,name,email',
                'resolvedBy:id,name,email'
            ]);
    
            // ✅ Appliquer les filtres dynamiquement
            $filters = $request->only([
                'status', 'priority', 'blockage_type', 'blockable_type',
                'is_escalated', 'assigned_to', 'created_by', 'date_from', 'date_to'
            ]);
    
            foreach ($filters as $key => $value) {
                if ($value !== null && $value !== '') {
                    switch ($key) {
                        case 'date_from':
                            $query->whereDate('created_at', '>=', $value);
                            break;
                        case 'date_to':
                            $query->whereDate('created_at', '<=', $value);
                            break;
                        case 'is_escalated':
                            $query->where('is_escalated', $request->boolean('is_escalated'));
                            break;
                        default:
                            $query->where($key, $value);
                            break;
                    }
                }
            }
    
            // ✅ Tri
            $sortBy = $request->sort_by ?? 'created_at';
            $sortDirection = $request->sort_direction ?? 'desc';
            $query->orderBy($sortBy, $sortDirection);
    
            // ✅ Pagination
            $perPage = $request->per_page ?? 15;
            $blockages = $query->paginate($perPage);
    
            // ✅ Transformer la collection sans casser la pagination
            $blockages->getCollection()->transform(function ($blockage) {
                return [
                    'id' => $blockage->id,
                    'name' => $blockage->name,
                    'description' => $blockage->description,
                    'blockage_type' => $blockage->blockage_type,
                    'status' => $blockage->status,
                    'priority' => $blockage->priority,
                    'is_blocking' => $blockage->is_blocking,
                    'is_escalated' => $blockage->is_escalated,
                    'blockable_type' => $blockage->blockable_type,
                    'blockable_id' => $blockage->blockable_id,
                    'pipeline_stageable_type' => $blockage->pipeline_stageable_type,
                    'pipeline_stageable_id' => $blockage->pipeline_stageable_id,
                    'created_at' => $blockage->created_at,
                    'updated_at' => $blockage->updated_at,
                    'resolved_at' => $blockage->resolved_at,
                    'escalated_at' => $blockage->escalated_at,
                    'assigned_user' => $blockage->assignedUser,
                    'created_by_user' => $blockage->createdByUser,
                    'resolved_by_user' => $blockage->resolvedBy,
                    // Infos calculées
                    'days_since_creation' => $blockage->created_at ? $blockage->created_at->diffInDays(now()) : 0,
'is_overdue' => $blockage->created_at ? $blockage->created_at->addDays(7)->lt(now()) : false,                ];
            });
    
            // ✅ Statistiques globales (optimisées avec groupBy)
            $statistics = [
                'total' => Blockage::count(),
                'by_status' => [
                    'active' => Blockage::whereIn('status', ['actif', 'open', 'in_progress'])->count(),
                    'resolved' => Blockage::whereIn('status', ['resolu', 'resolved'])->count(),
                    'cancelled' => Blockage::whereIn('status', ['annule', 'cancelled'])->count(),
                ],
                'by_priority' => [
                    'low' => Blockage::where('priority', 'low')->count(),
                    'medium' => Blockage::where('priority', 'medium')->count(),
                    'high' => Blockage::where('priority', 'high')->count(),
                    'critical' => Blockage::where('priority', 'critical')->count(),
                ],
                'by_type' => [
                    'process' => Blockage::where('blockage_type', 'process')->count(),
                    'data' => Blockage::where('blockage_type', 'data')->count(),
                    'technical' => Blockage::where('blockage_type', 'technical')->count(),
                    'other' => Blockage::where('blockage_type', 'other')->count(),
                ],
                'escalated_count' => Blockage::where('is_escalated', true)->count(),
                'unassigned_count' => Blockage::whereNull('assigned_to')->count(),
            ];
    
            return response()->json([
                'success' => true,
                'data' => $blockages, // ✅ conserve toute la pagination Laravel
                'statistics' => $statistics,
                'filters_applied' => $filters
            ]);
    
        } catch (\Exception $e) {
            \Log::error('Erreur index blockages:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des blockages: ' . $e->getMessage()
            ], 500);
        }
    }

  
    public function show(Blockage $blockage)
    {
        try {
            // Charger toutes les relations nécessaires
            $blockage->load([
                'assignedUser:id,name,email,created_at',
                'createdByUser:id,name,email,created_at', 
                'resolvedBy:id,name,email,created_at',
            ]);
    
            // Enrichir les données avec des informations calculées
            $blockageDetails = [
                // Informations de base
                'id' => $blockage->id,
                'name' => $blockage->name,
                'description' => $blockage->description,
                'blockage_type' => $blockage->blockage_type,
                'status' => $blockage->status,
                'priority' => $blockage->priority,
                
                // États et flags
                'is_blocking' => $blockage->is_blocking,
                'is_escalated' => $blockage->is_escalated,
                
                // Relations polymorphiques avec détails
                'blockable_type' => $blockage->blockable_type,
                'blockable_id' => $blockage->blockable_id,
                'blockable_details' => $this->getBlockableDetails($blockage->blockable_type, $blockage->blockable_id),
                
                'pipeline_stageable_type' => $blockage->pipeline_stageable_type,
                'pipeline_stageable_id' => $blockage->pipeline_stageable_id,
                'pipeline_stageable_details' => $this->getPipelineStageableDetails($blockage->pipeline_stageable_type, $blockage->pipeline_stageable_id),
                
                // Dates importantes
                'created_at' => $blockage->created_at,
                'updated_at' => $blockage->updated_at,
                'resolved_at' => $blockage->resolved_at,
                'escalated_at' => $blockage->escalated_at,
                
                // Relations utilisateurs
                'assigned_user' => $blockage->assignedUser ? [
                    'id' => $blockage->assignedUser->id,
                    'name' => $blockage->assignedUser->name,
                    'email' => $blockage->assignedUser->email,
                    'assigned_since' => $blockage->updated_at ? $blockage->updated_at->diffForHumans() : null
                ] : null,
                
                'created_by_user' => $blockage->createdByUser ? [
                    'id' => $blockage->createdByUser->id,
                    'name' => $blockage->createdByUser->name,
                    'email' => $blockage->createdByUser->email,
                    'created_date' => $blockage->created_at && is_object($blockage->created_at) ? 
                        $blockage->created_at->toDateTimeString() : $blockage->created_at
                ] : null,
                
                'resolved_by_user' => $blockage->resolvedBy ? [
                    'id' => $blockage->resolvedBy->id,
                    'name' => $blockage->resolvedBy->name,
                    'email' => $blockage->resolvedBy->email,
                    'resolved_date' => $blockage->resolved_at && is_object($blockage->resolved_at) ? 
                        $blockage->resolved_at->toDateTimeString() : $blockage->resolved_at
                ] : null,
                
                // Informations calculées et métriques
                'metrics' => [
                    'days_since_creation' => $blockage->created_at && is_object($blockage->created_at) ? 
                        $blockage->created_at->diffInDays(now()) : 0,
                    'hours_since_creation' => $blockage->created_at && is_object($blockage->created_at) ? 
                        $blockage->created_at->diffInHours(now()) : 0,
                    'is_overdue' => $blockage->created_at && is_object($blockage->created_at) ? 
                        $blockage->created_at->addDays(7)->lt(now()) : false,
                    'time_to_resolution' => $blockage->resolved_at && $blockage->created_at && 
                        is_object($blockage->resolved_at) && is_object($blockage->created_at) ? 
                        $blockage->created_at->diffInHours($blockage->resolved_at) : null,
                    'time_to_escalation' => $blockage->escalated_at && $blockage->created_at &&
                        is_object($blockage->escalated_at) && is_object($blockage->created_at) ?
                        $blockage->created_at->diffInHours($blockage->escalated_at) : null,
                ],
                
                // Timeline simple des événements
                'timeline' => [
                    'created' => $blockage->created_at && is_object($blockage->created_at) ? 
                        $blockage->created_at->toDateTimeString() : $blockage->created_at,
                    'escalated' => $blockage->escalated_at && is_object($blockage->escalated_at) ? 
                        $blockage->escalated_at->toDateTimeString() : $blockage->escalated_at,
                    'resolved' => $blockage->resolved_at && is_object($blockage->resolved_at) ? 
                        $blockage->resolved_at->toDateTimeString() : $blockage->resolved_at,
                ],
            ];
    
            return response()->json([
                'success' => true,
                'data' => $blockageDetails,
                'message' => 'Détails du blockage récupérés avec succès'
            ]);
    
        } catch (\Exception $e) {
            \Log::error('Erreur show blockage:', [
                'blockage_id' => $blockage->id ?? 'unknown',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des détails du blockage: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Récupérer les détails de l'entité bloquée
     */
    private function getBlockableDetails($blockableType, $blockableId)
    {
        try {
            switch ($blockableType) {
                case 'investisseur':
                    $entity = \App\Models\Investisseur::find($blockableId);
                    if ($entity) {
                        return [
                            'id' => $entity->id,
                            'nom' => $entity->nom ?? null,
                            'prenom' => $entity->prenom ?? null,
                            'email' => $entity->email ?? null,
                            'telephone' => $entity->telephone ?? null,
                            'statut' => $entity->statut ?? null,
                            'type_investisseur' => $entity->type_investisseur ?? null,
                            'montant_disponible' => $entity->montant_disponible ?? null,
                            'created_at' => $entity->created_at ?? null,
                        ];
                    }
                    break;
    
                case 'prospect':
                    $entity = \App\Models\Prospect::find($blockableId);
                    if ($entity) {
                        return [
                            'id' => $entity->id,
                            'nom' => $entity->nom ?? null,
                            'prenom' => $entity->prenom ?? null,
                            'email' => $entity->email ?? null,
                            'telephone' => $entity->telephone ?? null,
                            'statut' => $entity->statut ?? null,
                            'entreprise' => $entity->entreprise ?? null,
                            'created_at' => $entity->created_at ?? null,
                        ];
                    }
                    break;
    
                case 'projet':
                    $entity = \App\Models\Project::find($blockableId);
                    if ($entity) {
                        return [
                            'id' => $entity->id,
                            'title' => $entity->title ?? null,
                            'description' => $entity->description ?? null,
                            'company_name' => $entity->company_name ?? null,
                            'status' => $entity->status ?? null,
                            'investment_amount' => $entity->investment_amount ?? null,
                            'jobs_expected' => $entity->jobs_expected ?? null,
                            'secteur_id' => $entity->secteur_id ?? null,
                            'region_id' => $entity->region_id ?? null,
                            'created_at' => $entity->created_at ?? null,
                        ];
                    }
                    break;
    
                case 'invite':
                    $entity = \App\Models\Invite::find($blockableId);
                    if ($entity) {
                        return [
                            'id' => $entity->id,
                            'nom' => $entity->nom ?? null,
                            'prenom' => $entity->prenom ?? null,
                            'email' => $entity->email ?? null,
                            'telephone' => $entity->telephone ?? null,
                            'statut' => $entity->statut ?? null,
                            'type_invite' => $entity->type_invite ?? null,
                            'date_evenement' => $entity->date_evenement ?? null,
                            'created_at' => $entity->created_at ?? null,
                        ];
                    }
                    break;
    
                default:
                    return [
                        'error' => 'Type d\'entité non reconnu',
                        'type' => $blockableType
                    ];
            }
    
            return [
                'error' => 'Entité non trouvée',
                'type' => $blockableType,
                'id' => $blockableId
            ];
    
        } catch (\Exception $e) {
            \Log::warning("Erreur récupération blockable details: " . $e->getMessage());
            return [
                'error' => 'Erreur lors de la récupération des détails',
                'type' => $blockableType,
                'id' => $blockableId
            ];
        }
    }
    
    /**
     * Récupérer les détails de l'étape du pipeline
     */
    private function getPipelineStageableDetails($pipelineStageableType, $pipelineStageableId)
    {
        try {
            // Si le type est vide, essayer de déterminer automatiquement
            if (empty($pipelineStageableType)) {
                // Essayer de trouver dans les différents types d'étapes
                $stages = [
                    'InvestisseurPipelineStage' => \App\Models\InvestorPipelineStage::class,
                    'ProspectPipelineStage' => \App\Models\ProspectPipelineStage::class,
                    'ProjectPipelineStage' => \App\Models\ProjectPipelineStage::class,
                    'InvitePipelineStage' => \App\Models\InvitePipelineStage::class,
                ];
    
                foreach ($stages as $className => $modelClass) {
                    if (class_exists($modelClass)) {
                        $entity = $modelClass::find($pipelineStageableId);
                        if ($entity) {
                            return [
                                'id' => $entity->id,
                                'name' => $entity->name ?? null,
                                'description' => $entity->description ?? null,
                                'order' => $entity->order ?? null,
                                'type' => $className,
                                'created_at' => $entity->created_at ?? null,
                            ];
                        }
                    }
                }
            } else {
                // Utiliser le type spécifié
                $modelClass = "\\App\\Models\\{$pipelineStageableType}";
                if (class_exists($modelClass)) {
                    $entity = $modelClass::find($pipelineStageableId);
                    if ($entity) {
                        return [
                            'id' => $entity->id,
                            'name' => $entity->name ?? null,
                            'description' => $entity->description ?? null,
                            'order' => $entity->order ?? null,
                            'type' => $pipelineStageableType,
                            'created_at' => $entity->created_at ?? null,
                        ];
                    }
                }
            }
    
            return [
                'error' => 'Étape de pipeline non trouvée',
                'type' => $pipelineStageableType,
                'id' => $pipelineStageableId
            ];
    
        } catch (\Exception $e) {
            \Log::warning("Erreur récupération pipeline stageable details: " . $e->getMessage());
            return [
                'error' => 'Erreur lors de la récupération des détails de l\'étape',
                'type' => $pipelineStageableType,
                'id' => $pipelineStageableId
            ];
        }
    }
    

 
    /**
     * Récupérer les blockages d'une entité spécifique dans une étape donnée
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    // public function getBlockagesByStage(Request $request, $entityType, $entityId, $stageId)
    // {
    //     try {
    //         $blockableType = match($entityType) {
    //             'invite' => \App\Models\Invite::class,
    //             'prospect' => \App\Models\Prospect::class,
    //             'investor' => \App\Models\Investisseur::class,
    //             'projet' => \App\Models\Project::class,
    //             default => throw new \Exception("Type d'entité non valide")
    //         };  

    //         $blockages = $this->service->getBlockagesForStage($blockableType, $entityId, $stageId);

    //         return response()->json([
    //             'success' => true,
    //             'data' => $blockages,
    //             'message' => "Blocages récupérés avec succès"
    //         ]);
    //     } catch (\Exception $e) {
    //         \Log::error("Erreur récupération blocages: ".$e->getMessage());
    //         return response()->json([
    //             'success' => false,
    //             'message' => "Erreur lors de la récupération des blocages",
    //             'error' => config('app.debug') ? $e->getMessage() : null
    //         ], 500);
    //     }
    // }
    public function getBlockages($entityType, $entityId, $stageId, BlockageService $blockageService)
{
    try {
        $blockages = $blockageService->getBlockagesForStage($entityType, $entityId, $stageId);

        return response()->json([
            'success' => true,
            'data' => $blockages,
            'message' => 'Blocages récupérés avec succès'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des blocages',
            'error'   => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

     
     
public function getByStage(Request $request)
{
    try {
        // Validation des paramètres
        $request->validate([
            'blockable_type' => 'required|string|in:invite,prospect,investisseur,projet',
            'blockable_id' => 'required|integer',
            'pipeline_stageable_id' => 'required|integer',
            'priority' => 'nullable|string|in:low,medium,high,critical',
            'blockage_type' => 'nullable|string|in:process,data,technical,other',
        ]);

        // ✅ CORRECTION : Construire la requête exactement comme dans le debug
        $query = Blockage::where('blockable_type', $request->blockable_type)
            ->where('blockable_id', $request->blockable_id)
            ->where('pipeline_stageable_id', $request->pipeline_stageable_id);

        // Filtres optionnels
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('blockage_type')) {
            $query->where('blockage_type', $request->blockage_type);
        }

        // ✅ CORRECTION : Récupérer les blockages SANS les relations d'abord
        $blockages = $query->orderBy('created_at', 'desc')->get();

        // ✅ CORRECTION : Ajouter les relations après si nécessaire
        $blockages->load(['assignedUser:id,name,email', 'createdByUser:id,name,email', 'resolvedBy:id,name,email']);

        // Enrichir les données
        $enrichedBlockages = $blockages->map(function ($blockage) {
            return [
                'id' => $blockage->id,
                'name' => $blockage->name,
                'description' => $blockage->description,
                'blockage_type' => $blockage->blockage_type,
                'status' => $blockage->status,
                'priority' => $blockage->priority,
                'is_blocking' => $blockage->is_blocking,
                'is_escalated' => $blockage->is_escalated,
                'blockable_type' => $blockage->blockable_type,
                'blockable_id' => $blockage->blockable_id,
                'pipeline_stageable_type' => $blockage->pipeline_stageable_type,
                'pipeline_stageable_id' => $blockage->pipeline_stageable_id,
                'created_at' => $blockage->created_at,
                'resolved_at' => $blockage->resolved_at,
                'escalated_at' => $blockage->escalated_at,
                'assigned_user' => $blockage->assignedUser,
                'created_by_user' => $blockage->createdByUser,
                'resolved_by_user' => $blockage->resolvedBy,
                // ✅ CORRECTION : Gestion sécurisée des méthodes
                'is_overdue' => method_exists($blockage, 'isOverdue') ? $blockage->isOverdue() : false,
                'days_since_creation' => $blockage->created_at ? $blockage->created_at->diffInDays(now()) : 0,
            ];
        });

        // Calculer des statistiques
        $statistics = [
            'total' => $blockages->count(),
            'by_status' => [
                'active' => $blockages->whereIn('status', ['actif', 'open', 'in_progress'])->count(),
                'resolved' => $blockages->whereIn('status', ['resolu', 'resolved'])->count(),
                'cancelled' => $blockages->whereIn('status', ['annule', 'cancelled'])->count(),
            ],
            'by_priority' => [
                'low' => $blockages->where('priority', 'low')->count(),
                'medium' => $blockages->where('priority', 'medium')->count(),
                'high' => $blockages->where('priority', 'high')->count(),
                'critical' => $blockages->where('priority', 'critical')->count(),
            ],
            'escalated_count' => $blockages->where('is_escalated', true)->count(),
        ];

        return response()->json([
            'success' => true,
            'debug_info' => [
                'query_sql' => $query->toSql(),
                'query_bindings' => $query->getBindings(),
                'total_found' => $blockages->count(),
            ],
            'data' => [
                'blockages' => $enrichedBlockages,
                'statistics' => $statistics,
                'stage_info' => [
                    'entity_type' => $request->blockable_type,
                    'entity_id' => $request->blockable_id,
                    'stage_id' => $request->pipeline_stageable_id,
                ]
            ]
        ]);

    } catch (\Exception $e) {
        \Log::error('Erreur getByStage:', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des blockages: ' . $e->getMessage()
        ], 500);
    }
}

    
    

    public function store(Request $request)
{
    $data = $request->validate([
        'name' => 'required|string',
        'description' => 'nullable|string',
        'blockable_type' => 'required|string',
        'blockable_id' => 'required|integer',
        'pipeline_stageable_type' => 'required|string',
        'pipeline_stageable_id' => 'required|integer',
        'blockage_type' => 'required|in:process,data,technical,other',
        'status' => 'in:actif,resolu,annule',
        'priority' => 'in:low,medium,high,critical',
        'assigned_to' => 'nullable|exists:users,id',
    ]);

   

    $data['created_by'] = auth()->id();

    $blockage = $this->service->create($data);

    return response()->json(['success' => true, 'data' => $blockage]);
}

    public function update(Request $request, Blockage $blockage)
    {
        $data = $request->only(['name', 'description', 'blockage_type', 'status', 'priority', 'assigned_to']);
        $blockage = $this->service->update($blockage, $data);
        return response()->json(['success' => true, 'data' => $blockage]);
    }

    public function resolve(Blockage $blockage, Request $request)
{
    $resolvedBy = auth()->id();

    if (!$resolvedBy) {
        return response()->json([
            'success' => false,
            'message' => 'Utilisateur non authentifié'
        ], 401);
    }

    $blockage = $this->service->resolve($blockage, $resolvedBy);

    return response()->json(['success' => true, 'data' => $blockage]);
}

    public function destroy(Blockage $blockage)
    {
        $this->service->delete($blockage);
        return response()->json(['success' => true, 'message' => 'Blocage supprimé']);
    }

    
    public function escalate(Request $request, Blockage $blockage)
    {
        try {
            // Admin fixe avec ID 1
            $adminId = 1;
            
            // Vérifier que l'admin existe
            $admin = \App\Models\User::find($adminId);
            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Administrateur non trouvé'
                ], 404);
            }
    
            // Mettre à jour le blocage sans envoyer de notification pour l'instant
            $blockage->update([
                'priority'     => 'critical',
                'is_escalated' => true,
                'escalated_at' => now(),
                'assigned_to'  => $adminId
            ]);
            
            return response()->json([
                'success' => true, 
                'message' => 'Blocage escaladé avec succès à l\'administrateur',
                'data' => $blockage->load(['assignedUser'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'escalade du blocage: ' . $e->getMessage()
            ], 500);
        }
    }
}
