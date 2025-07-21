<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Models\Entreprise;
use App\Http\Requests\InviteRequest;
use App\Exceptions\SuivieProjet\InviteExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
}