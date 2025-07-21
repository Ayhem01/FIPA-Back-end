<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etape;
use App\Models\Action;
use App\Http\Requests\EtapeRequest;
use App\Exceptions\EtapeExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EtapeController extends Controller
{
    /**
     * Liste des étapes avec filtres possibles
     */
    public function index(Request $request)
    {
        try {
            $query = Etape::query()->with(['action']);
            
            // Filtres
            if ($request->has('action_id')) {
                $query->where('action_id', $request->action_id);
            }
            
            // Tri
            $sortField = $request->sort_by ?? 'ordre';
            $sortDirection = $request->sort_direction ?? 'asc';
            $etapes = $query->orderBy($sortField, $sortDirection)
                           ->get();
            
            return response()->json([
                'success' => true,
                'data' => $etapes
            ]);
            
        } catch (\Exception $e) {
            return EtapeExceptionHandler::handle($e);
        }
    }

    /**
     * Afficher une étape spécifique
     */
    public function show($id)
    {
        try {
            $etape = Etape::with(['action', 'invites'])->findOrFail($id);
            
            // Statistiques
            $etape->invites_count = $etape->invites()->count();
            
            return response()->json([
                'success' => true,
                'data' => $etape
            ]);
            
        } catch (\Exception $e) {
            return EtapeExceptionHandler::handle($e);
        }
    }

    /**
     * Créer une nouvelle étape
     */
    public function store(EtapeRequest $request)
    {
        try {
            // Vérifier si l'action existe
            $action = Action::findOrFail($request->action_id);
            
            // Déterminer l'ordre si non fourni
            $data = $request->validated();
            if (!isset($data['ordre']) || $data['ordre'] === null) {
                $maxOrdre = Etape::where('action_id', $request->action_id)->max('ordre');
                $data['ordre'] = ($maxOrdre !== null) ? $maxOrdre + 1 : 1;
            }
            
            $etape = Etape::create($data);
            
            return response()->json([
                'success' => true,
                'message' => 'Étape créée avec succès',
                'data' => $etape
            ], 201);
            
        } catch (\Exception $e) {
            return EtapeExceptionHandler::handle($e);
        }
    }

    /**
     * Mettre à jour une étape
     */
    public function update(EtapeRequest $request, $id)
    {
        try {
            $etape = Etape::findOrFail($id);
            $etape->update($request->validated());
            
            return response()->json([
                'success' => true,
                'message' => 'Étape mise à jour avec succès',
                'data' => $etape
            ]);
            
        } catch (\Exception $e) {
            return EtapeExceptionHandler::handle($e);
        }
    }

    /**
     * Supprimer une étape
     */
    public function destroy($id)
    {
        try {
            $etape = Etape::findOrFail($id);
            
            // Vérifier si des invités sont associés à cette étape
            $invitesCount = $etape->invites()->count();
            if ($invitesCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer cette étape car elle est associée à ' . $invitesCount . ' invité(s)'
                ], 409);
            }
            
            $etape->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Étape supprimée avec succès'
            ]);
            
        } catch (\Exception $e) {
            return EtapeExceptionHandler::handle($e);
        }
    }

    /**
     * Obtenir les étapes pour une action spécifique
     */
    public function getByAction($actionId)
    {
        try {
            $action = Action::findOrFail($actionId);
            
            $etapes = $action->etapes()->orderBy('ordre')->get();
            
            return response()->json([
                'success' => true,
                'data' => $etapes
            ]);
            
        } catch (\Exception $e) {
            return EtapeExceptionHandler::handle($e);
        }
    }

    /**
     * Réorganiser l'ordre des étapes
     */
    public function reorder(Request $request)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'etapes' => 'required|array',
                'etapes.*.id' => 'required|exists:etapes,id',
                'etapes.*.ordre' => 'required|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();
            
            foreach ($request->etapes as $etapeData) {
                Etape::where('id', $etapeData['id'])->update(['ordre' => $etapeData['ordre']]);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Ordre des étapes mis à jour avec succès'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return EtapeExceptionHandler::handle($e);
        }
    }
}