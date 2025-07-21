<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entreprise;
use App\Http\Requests\EntrepriseRequest;
use App\Exceptions\EntrepriseExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EntrepriseController extends Controller
{
    /**
     * Liste des entreprises avec filtres
     */
    public function index(Request $request)
    {
        try {
            $query = Entreprise::query()->with(['secteur', 'proprietaire']);
            
            // Filtres
            if ($request->has('nom')) {
                $query->where('nom', 'like', '%' . $request->nom . '%');
            }
            
            if ($request->has('secteur_id')) {
                $query->where('secteur_id', $request->secteur_id);
            }
            
            if ($request->has('statut')) {
                $query->where('statut', $request->statut);
            }
            
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }
            
            if ($request->has('pipeline_stage_id')) {
                $query->where('pipeline_stage_id', $request->pipeline_stage_id);
            }
            
            if ($request->has('proprietaire_id')) {
                $query->where('proprietaire_id', $request->proprietaire_id);
            }
            
            // Tri
            $sortField = $request->sort_by ?? 'created_at';
            $sortDirection = $request->sort_direction ?? 'desc';
            $entreprises = $query->orderBy($sortField, $sortDirection)
                                ->paginate($request->per_page ?? 15);
            
            return response()->json([
                'success' => true,
                'data' => $entreprises
            ]);
            
        } catch (\Exception $e) {
            return EntrepriseExceptionHandler::handle($e);
        }
    }

    /**
     * Afficher une entreprise spécifique
     */
    public function show($id)
    {
        try {
            $entreprise = Entreprise::with([
                'secteur', 
                'proprietaire', 
                'pipelineStage', 
                'pipelineType', 
                'contacts',
                'invites' => function($q) {
                    $q->orderBy('created_at', 'desc')->take(5);
                },
                'projets' => function($q) {
                    $q->orderBy('created_at', 'desc')->take(5);
                }
            ])->findOrFail($id);
            
            // Ajouter des statistiques
            $entreprise->contacts_count = $entreprise->contacts()->count();
            $entreprise->invites_count = $entreprise->invites()->count();
            $entreprise->projets_count = $entreprise->projets()->count();
            
            return response()->json([
                'success' => true,
                'data' => $entreprise
            ]);
            
        } catch (\Exception $e) {
            return EntrepriseExceptionHandler::handle($e);
        }
    }

    /**
     * Créer une nouvelle entreprise
     */
    public function store(EntrepriseRequest $request)
    {
        try {
            $data = $request->validated();
            
            // Traitement du logo si fourni
            if ($request->hasFile('logo')) {
                $path = $request->file('logo')->store('logos', 'public');
                $data['logo'] = $path;
            }
            
            $entreprise = Entreprise::create($data);
            
            return response()->json([
                'success' => true,
                'message' => 'Entreprise créée avec succès',
                'data' => $entreprise
            ], 201);
            
        } catch (\Exception $e) {
            return EntrepriseExceptionHandler::handle($e);
        }
    }

    /**
     * Mettre à jour une entreprise
     */
    public function update(EntrepriseRequest $request, $id)
    {
        try {
            $entreprise = Entreprise::findOrFail($id);
            $data = $request->validated();
            
            // Traitement du logo si fourni
            if ($request->hasFile('logo')) {
                // Supprimer l'ancien logo si existant
                if ($entreprise->logo) {
                    Storage::disk('public')->delete($entreprise->logo);
                }
                
                $path = $request->file('logo')->store('logos', 'public');
                $data['logo'] = $path;
            }
            
            $entreprise->update($data);
            
            return response()->json([
                'success' => true,
                'message' => 'Entreprise mise à jour avec succès',
                'data' => $entreprise
            ]);
            
        } catch (\Exception $e) {
            return EntrepriseExceptionHandler::handle($e);
        }
    }

    /**
     * Supprimer une entreprise
     */
    public function destroy($id)
    {
        try {
            $entreprise = Entreprise::findOrFail($id);
            
            // Vérifier s'il y a des projets liés actifs
            $projetsActifs = $entreprise->projets()->whereNotIn('statut', ['terminé', 'abandonné'])->count();
            if ($projetsActifs > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer cette entreprise car elle possède des projets actifs'
                ], 409);
            }
            
            // Supprimer le logo si existant
            if ($entreprise->logo) {
                Storage::disk('public')->delete($entreprise->logo);
            }
            
            $entreprise->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Entreprise supprimée avec succès'
            ]);
            
        } catch (\Exception $e) {
            return EntrepriseExceptionHandler::handle($e);
        }
    }

    /**
     * Mettre à jour l'étape de pipeline d'une entreprise
     */
    public function updatePipelineStage(Request $request, $id)
    {
        try {
            $entreprise = Entreprise::findOrFail($id);
            
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'pipeline_stage_id' => 'required|exists:pipeline_stages,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $entreprise->pipeline_stage_id = $request->pipeline_stage_id;
            $entreprise->save();
            
            // Récupérer l'étape avec ses informations
            $entreprise->load('pipelineStage');
            
            return response()->json([
                'success' => true,
                'message' => 'Étape de pipeline mise à jour',
                'data' => $entreprise
            ]);
            
        } catch (\Exception $e) {
            return EntrepriseExceptionHandler::handle($e);
        }
    }

    /**
     * Recherche rapide d'entreprises
     */
    public function search(Request $request)
    {
        try {
            $term = $request->term;
            $limit = $request->limit ?? 10;
            
            if (empty($term)) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }
            
            $entreprises = Entreprise::where('nom', 'like', '%' . $term . '%')
                ->orWhere('email', 'like', '%' . $term . '%')
                ->select('id', 'nom', 'logo', 'email')
                ->limit($limit)
                ->get();
                
            return response()->json([
                'success' => true,
                'data' => $entreprises
            ]);
            
        } catch (\Exception $e) {
            return EntrepriseExceptionHandler::handle($e);
        }
    }
    
    /**
     * Liste des entreprises avec leurs statistiques pour le dashboard
     */
    public function stats()
    {
        try {
            // Stats par statut
            $statsByStatus = Entreprise::selectRaw('statut, COUNT(*) as count')
                ->groupBy('statut')
                ->get();
                
            // Stats par secteur
            $statsBySector = Entreprise::selectRaw('secteurs.name as secteur, COUNT(entreprises.id) as count')
                ->join('secteurs', 'entreprises.secteur_id', '=', 'secteurs.id')
                ->groupBy('secteurs.name')
                ->get();
                
            // Stats par propriétaire
            $statsByOwner = Entreprise::selectRaw('users.name as proprietaire, COUNT(entreprises.id) as count')
                ->join('users', 'entreprises.proprietaire_id', '=', 'users.id')
                ->groupBy('users.name')
                ->get();
                
            // Nombre total
            $total = Entreprise::count();
            $nouveaux = Entreprise::where('created_at', '>=', now()->subDays(30))->count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'nouveaux' => $nouveaux,
                    'par_statut' => $statsByStatus,
                    'par_secteur' => $statsBySector,
                    'par_proprietaire' => $statsByOwner
                ]
            ]);
            
        } catch (\Exception $e) {
            return EntrepriseExceptionHandler::handle($e);
        }
    }
}