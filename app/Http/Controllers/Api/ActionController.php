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

            $invitesCount = $action->invites()->count();
            if ($invitesCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer cette action car elle possède ' . $invitesCount . ' invité(s)'
                ], 409);
            }

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
                if ($entity) $entity->delete();
            }

            $action->delete();

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

            $events = $actions->map(function($action) {
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
}