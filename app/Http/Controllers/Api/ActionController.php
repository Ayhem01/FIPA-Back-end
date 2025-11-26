<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Action;
use App\Models\Entreprise;
use App\Models\Media;
use App\Models\CTE;
use App\Models\Delegations;
use App\Models\VisitesEntreprise;
use App\Models\SalonSectoriels;
use App\Models\DemarchageDirect;
use App\Models\SeminaireJIPays;
use App\Models\SeminairesJISecteur;
use App\Models\Salons;
use App\Http\Requests\ActionRequest;
use App\Exceptions\SuivieProjet\ActionExceptionHandler;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ActionController extends Controller
{
    /**
     * Liste des actions avec filtres
     */

    public function index(Request $request)
    {
        try {
            $query = Action::query();

            // Charger dynamiquement les relations spécifiques au type
            $query->with([
                'responsable',
                // 'entreprise',
                'media',
                'cte',
                'delegation',
                'visiteEntreprise',
                'salonSectoriel',
                'demarchageDirect',
                'seminaireJIPays',
                'seminaireJISecteur',
                'salon'
            ]);

            // Filtres
            if ($request->has('nom')) {
                $query->where('nom', 'like', '%' . $request->nom . '%');
            }
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }
            if ($request->has('statut')) {
                $query->where('statut', $request->statut);
            }
            if ($request->has('responsable_id')) {
                $query->where('responsable_id', $request->responsable_id);
            }
            if ($request->has('periode')) {
                switch ($request->periode) {
                    case 'a_venir':
                        $query->aVenir();
                        break;
                    case 'passees':
                        $query->passees();
                        break;
                    case 'semaine':
                        $query->whereBetween('date_debut', [now(), now()->addDays(7)]);
                        break;
                    case 'mois':
                        $query->whereBetween('date_debut', [now(), now()->addMonth()]);
                        break;
                }
            }

            // Tri
            $sortField = $request->sort_by ?? 'date_debut';
            $sortDirection = $request->sort_direction ?? 'asc';
            $actions = $query->orderBy($sortField, $sortDirection)
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'data' => $actions
            ]);
        } catch (\Exception $e) {
            return ActionExceptionHandler::handle($e);
        }
    }

    /**
     * Afficher une action spécifique
     */


    public function show($id)
    {
        try {
            // Récupérer juste l'action pour déterminer son type
            $action = Action::findOrFail($id);
            $type = $action->type;

            // Définir les relations à charger en fonction du type
            $relations = [
                'responsable',
                'etapes',
                'invites' => function ($q) {
                    $q->orderBy('created_at', 'desc');
                }
            ];

            // Ajouter uniquement la relation correspondant au type
            $typeRelationMap = [
                'media' => 'media',
                'cte' => 'cte',
                'delegation' => 'delegation',
                'visite_entreprise' => 'visiteEntreprise',
                'salon_sectoriel' => 'salonSectoriel',
                'demarchage_direct' => 'demarchageDirect',
                'seminaire_jipays' => 'seminaireJIPays',
                'seminaire_jisecteur' => 'seminaireJISecteur',
                'salon' => 'salon'
            ];

            if (isset($typeRelationMap[$type])) {
                $relations[] = $typeRelationMap[$type];
            }

            // Recharger avec les relations pertinentes
            $action = Action::with($relations)->findOrFail($id);

            // Ajouter les statistiques
            $action->invites_count = $action->invites()->count();
            $action->invites_confirmes_count = $action->invitesConfirmesCount;

            // Ajouter le timing
            $now = Carbon::now();
            if ($now->lt($action->date_debut)) {
                $action->timing = 'a_venir';
            } elseif ($now->gt($action->date_fin ?? $action->date_debut)) {
                $action->timing = 'passee';
            } else {
                $action->timing = 'en_cours';
            }

            return response()->json([
                'success' => true,
                'data' => $action
            ]);
        } catch (\Exception $e) {
            return ActionExceptionHandler::handle($e);
        }
    }

    /**
     * Créer une nouvelle action et son entité spécifique
     */
    public function store(ActionRequest $request)
    {
        Log::info('Données reçues pour création d\'action:', $request->all());

        try {
            $action = Action::create($request->validated());

            // Mappe le type à son modèle
            $modelMap = [
                'media' => Media::class,
                'cte' => CTE::class,
                'delegation' => Delegations::class,
                'visite_entreprise' => VisitesEntreprise::class,
                'salon_sectoriel' => SalonSectoriels::class,
                'demarchage_direct' => DemarchageDirect::class,
                'seminaire_jipays' => SeminaireJIPays::class,
                'seminaire_jisecteur' => SeminairesJISecteur::class,
                'salon' => Salons::class,
            ];

            $type = $action->type;
            if (isset($modelMap[$type]) && method_exists($modelMap[$type], 'createFromAction')) {
                $modelMap[$type]::createFromAction($action, $request);
            }

            return response()->json([
                'success' => true,
                'message' => 'Action et entité spécifique créées avec succès',
                'data' => $action
            ], 201);
        } catch (\Exception $e) {
            return ActionExceptionHandler::handle($e);
        }
    }

    /**
     * Mettre à jour une action et son entité spécifique
     */

   public function update(ActionRequest $request, $id)
    {
        Log::info('Données reçues pour mise à jour d\'action:', $request->all());

        try {
            $action = Action::findOrFail($id);

            // Vérification autorisation
            $user = auth('api')->user() ?? auth()->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
            }
            if (!$this->canModifyAction($user, $action)) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }

            // 1. Mettre à jour l'action
            $action->update($request->validated());

            // 2. Récupérer et mettre à jour l'entité spécifique
            $modelMap = [
                'media' => Media::class,
                'cte' => CTE::class,
                'delegation' => Delegations::class,
                'visite_entreprise' => VisitesEntreprise::class,
                'salon_sectoriel' => SalonSectoriels::class,
                'demarchage_direct' => DemarchageDirect::class,
                'seminaire_jipays' => SeminaireJIPays::class,
                'seminaire_jisecteur' => SeminairesJISecteur::class,
                'salon' => Salons::class,
            ];

            $type = $action->type;

            if (isset($modelMap[$type])) {
                if (method_exists($modelMap[$type], 'updateFromAction')) {
                    $modelMap[$type]::updateFromAction($action, $request);
                } else {
                    // Approche générique si updateFromAction n'existe pas
                    $entityClass = $modelMap[$type];
                    $entity = $entityClass::where('action_id', $action->id)->first();

                    if ($entity) {
                        $fillableFields = (new $entityClass)->getFillable();
                        $data = $request->only($fillableFields);
                        $data['action_id'] = $action->id;
                        $entity->update($data);
                    } else if (method_exists($modelMap[$type], 'createFromAction')) {
                        $modelMap[$type]::createFromAction($action, $request);
                    }
                }
            }

            // 3. Déterminer la relation spécifique au type
            $relationName = null;
            $typeRelationMap = [
                'media' => 'media',
                'cte' => 'cte',
                'delegation' => 'delegation',
                'visite_entreprise' => 'visiteEntreprise',
                'salon_sectoriel' => 'salonSectoriel',
                'demarchage_direct' => 'demarchageDirect',
                'seminaire_jipays' => 'seminaireJIPays',
                'seminaire_jisecteur' => 'seminaireJISecteur',
                'salon' => 'salon'
            ];

            if (isset($typeRelationMap[$type])) {
                $relationName = $typeRelationMap[$type];
            }

            // 4. Charger les relations nécessaires
            $relations = ['responsable'];
            if ($relationName) {
                $relations[] = $relationName;
            }

            // 5. Recharger l'action avec ses relations
            $action = Action::with($relations)->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Action et entité spécifique mises à jour avec succès',
                'data' => $action
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise à jour de l\'action:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ActionExceptionHandler::handle($e);
        }
    }

    /**
     * Mettre à jour le statut d'une action
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $action = Action::findOrFail($id);

            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'statut' => 'required|in:planifiee,en_preparation,confirmee,en_cours,terminee,annulee'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $action->statut = $request->statut;
            $action->save();

            return response()->json([
                'success' => true,
                'message' => 'Statut de l\'action mis à jour',
                'data' => $action
            ]);
        } catch (\Exception $e) {
            return ActionExceptionHandler::handle($e);
        }
    }

    /**
     * Supprimer une action et son entité spécifique
     */
    public function destroy($id)
    {
        try {
            $action = Action::findOrFail($id);

            // Supprimer les invites liés avant l'action (cascade applicative)
            DB::transaction(function () use ($action) {
                // Supprimer les invités
                $action->invites()->delete();

                // Supprimer l'entité spécifique
                $modelMap = [
                    'media' => Media::class,
                    'cte' => CTE::class,
                    'delegation' => Delegations::class,
                    'visite_entreprise' => VisitesEntreprise::class,
                    'salon_sectoriel' => SalonSectoriels::class,
                    'demarchage_direct' => DemarchageDirect::class,
                    'seminaire_jipays' => SeminaireJIPays::class,
                    'seminaire_jisecteur' => SeminairesJISecteur::class,
                    'salon' => Salons::class,
                ];

                $type = $action->type;
                if (isset($modelMap[$type]) && method_exists($action, $type)) {
                    $entity = $action->$type;
                    if ($entity) {
                        $entity->delete();
                    }
                }

                // Supprimer l'action
                $action->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Action supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            return ActionExceptionHandler::handle($e);
        }
    }

    /**
     * Actions pour une entreprise spécifique
     */
    public function getByEntreprise($entrepriseId)
    {
        try {
            $entreprise = Entreprise::findOrFail($entrepriseId);

            $actions = $entreprise->actions()
                ->with(['responsable'])
                ->orderBy('date_debut')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $actions
            ]);
        } catch (\Exception $e) {
            return ActionExceptionHandler::handle($e);
        }
    }

    /**
     * Calendrier des actions (format adapté pour les calendriers)
     */
    public function calendar(Request $request)
    {
        try {
            $start = $request->start ? Carbon::parse($request->start) : Carbon::now()->startOfMonth();
            $end = $request->end ? Carbon::parse($request->end) : Carbon::now()->endOfMonth();

            $actions = Action::whereBetween('date_debut', [$start, $end])
                ->orWhereBetween('date_fin', [$start, $end])
                ->get();

            $events = $actions->map(function ($action) {
                $color = '';
                switch ($action->statut) {
                    case 'planifiee':
                        $color = '#3498db'; // bleu
                        break;
                    case 'en_preparation':
                        $color = '#f39c12'; // orange
                        break;
                    case 'confirmee':
                        $color = '#2ecc71'; // vert
                        break;
                    case 'en_cours':
                        $color = '#9b59b6'; // violet
                        break;
                    case 'terminee':
                        $color = '#7f8c8d'; // gris
                        break;
                    case 'annulee':
                        $color = '#e74c3c'; // rouge
                        break;
                }

                return [
                    'id' => $action->id,
                    'title' => $action->nom,
                    'start' => $action->date_debut ? $action->date_debut->format('Y-m-d\TH:i:s') : null,
                    'end' => $action->date_fin ? $action->date_fin->format('Y-m-d\TH:i:s') : null,
                    'color' => $color,
                    'url' => '/actions/' . $action->id,
                    'description' => $action->description,
                    'location' => $action->lieu,
                    'extendedProps' => [
                        'type' => $action->type,
                        'statut' => $action->statut,
                        'invites_count' => $action->invites()->count(),
                        'virtuel' => $action->virtuel ?? false
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $events
            ]);
        } catch (\Exception $e) {
            return ActionExceptionHandler::handle($e);
        }
    }

    /**
     * Récupérer les statistiques globales des actions
     */
    public function globalStats()
    {
        try {
            $totalActions = Action::count();
            $actionsByStatus = Action::select('statut', DB::raw('COUNT(*) as count'))
                ->groupBy('statut')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_actions' => $totalActions,
                    'actions_by_status' => $actionsByStatus
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques globales des actions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les actions par type
     */
    public function actionsByType()
    {
        try {
            $actionsByType = Action::select('type', DB::raw('COUNT(*) as count'))
                ->groupBy('type')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $actionsByType
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des actions par type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les actions par mois
     */
    public function actionsByMonth()
    {
        try {
            $actionsByMonth = Action::select(DB::raw('MONTH(date_debut) as month'), DB::raw('COUNT(*) as count'))
                ->groupBy(DB::raw('MONTH(date_debut)'))
                ->orderBy(DB::raw('MONTH(date_debut)'))
                ->get();

            return response()->json([
                'success' => true,
                'data' => $actionsByMonth
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des actions par mois',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les actions par responsable
     */
    public function actionsByResponsable()
    {
        try {
            $actionsByResponsable = DB::table('actions')
                ->join('users', 'actions.responsable_id', '=', 'users.id')
                ->select('users.name as responsable', DB::raw('COUNT(actions.id) as count'))
                ->groupBy('users.name')
                ->orderByDesc('count')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $actionsByResponsable
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des actions par responsable',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les actions par statut
     */
    public function actionsByStatus()
    {
        try {
            $actionsByStatus = Action::select('statut', DB::raw('COUNT(*) as count'))
                ->groupBy('statut')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $actionsByStatus
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des actions par statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les actions par type et par mois
     */
    public function actionsByTypeAndMonth()
    {
        try {
            $actionsByTypeAndMonth = Action::select(
                'type',
                DB::raw('MONTH(date_debut) as month'),
                DB::raw('COUNT(*) as count')
            )
                ->groupBy('type', DB::raw('MONTH(date_debut)'))
                ->orderBy('type')
                ->orderBy(DB::raw('MONTH(date_debut)'))
                ->get();

            return response()->json([
                'success' => true,
                'data' => $actionsByTypeAndMonth
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des actions par type et par mois',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les actions par type et par statut
     */
    public function actionsByTypeAndStatus()
    {
        try {
            $actionsByTypeAndStatus = Action::select(
                'type',
                'statut',
                DB::raw('COUNT(*) as count')
            )
                ->groupBy('type', 'statut')
                ->orderBy('type')
                ->orderBy('statut')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $actionsByTypeAndStatus
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des actions par type et par statut',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getActionsTreemapData()
    {
        try {
            $types = [
                'Media' => 'media',
                'CTE' => 'cte',
                'Visite Entreprise' => 'visiteEntreprise',
                'Délégation' => 'delegation',
                'Salon Sectoriel' => 'salonSectoriel',
                'Démarchage Direct' => 'demarchageDirect',
                'Séminaire Pays' => 'seminaireJIPays',
                'Séminaire Secteur' => 'seminaireJISecteur',
                'Salon' => 'salon',
            ];

            $data = [];

            foreach ($types as $typeName => $relation) {
                $actions = Action::whereHas($relation)->with([
                    $relation,
                    'invites.prospect.investisseur.projet'
                ])->get();

                $children = $actions->map(function ($action) use ($relation) {
                    $related = $action->$relation;
                    if ($related) {

                        $investmentSum = $action->invites
                            ->flatMap(function ($invite) {
                                return [$invite->prospect?->investisseur?->projet?->investment_amount ?? 0];
                            })
                            ->sum();


                        return [
                            'name' => $action->nom,
                            'value' => $investmentSum,
                        ];
                    }
                    return null;
                })->filter();

                $data[] = [
                    'name' => $typeName,
                    'children' => $children->values()->toArray(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données pour le Treemap',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
