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
