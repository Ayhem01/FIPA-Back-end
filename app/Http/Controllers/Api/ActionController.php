<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Action;
use App\Models\Entreprise;
use App\Http\Requests\ActionRequest;
use App\Exceptions\ActionExceptionHandler;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ActionController extends Controller
{
    /**
     * Liste des actions avec filtres
     */
    public function index(Request $request)
    {
        try {
            $query = Action::query()->with(['responsable', 'entreprise']);
            
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
            
            if ($request->has('entreprise_id')) {
                $query->where('entreprise_id', $request->entreprise_id);
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
            $action = Action::with([
                'responsable', 
                'entreprise',
                'etapes',
                'invites' => function($q) {
                    $q->orderBy('created_at', 'desc');
                }
            ])->findOrFail($id);
            
            // Ajouter des statistiques
            $action->invites_count = $action->invites()->count();
            $action->invites_confirmes_count = $action->invitesConfirmesCount;
            
            // Vérifier si l'action est à venir, en cours ou passée
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
     * Créer une nouvelle action
     */
    public function store(ActionRequest $request)
    {
        try {
            $action = Action::create($request->validated());
            
            return response()->json([
                'success' => true,
                'message' => 'Action créée avec succès',
                'data' => $action
            ], 201);
            
        } catch (\Exception $e) {
            return ActionExceptionHandler::handle($e);
        }
    }

    /**
     * Mettre à jour une action
     */
    public function update(ActionRequest $request, $id)
    {
        try {
            $action = Action::findOrFail($id);
            $action->update($request->validated());
            
            return response()->json([
                'success' => true,
                'message' => 'Action mise à jour avec succès',
                'data' => $action
            ]);
            
        } catch (\Exception $e) {
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
     * Supprimer une action
     */
    public function destroy($id)
    {
        try {
            $action = Action::findOrFail($id);
            
            // Vérifier si l'action a des invités
            $invitesCount = $action->invites()->count();
            if ($invitesCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer cette action car elle possède ' . $invitesCount . ' invité(s)'
                ], 409);
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
                    'start' => $action->date_debut->format('Y-m-d\TH:i:s'),
                    'end' => $action->date_fin ? $action->date_fin->format('Y-m-d\TH:i:s') : null,
                    'color' => $color,
                    'url' => '/actions/' . $action->id,
                    'description' => $action->description,
                    'location' => $action->lieu,
                    'extendedProps' => [
                        'type' => $action->type,
                        'statut' => $action->statut,
                        'invites_count' => $action->invites()->count(),
                        'virtuel' => $action->virtuel
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