<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeminaireJIPays;
use App\Http\Requests\SeminaireJIPaysRequest;
use App\Exceptions\SeminaireJIPaysExceptionHandler;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class SeminaireJIPaysController extends Controller
{
    public function index()
    {
        try {
            $seminaires = SeminaireJIPays::all();

            if ($seminaires->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun séminaire trouvé.'
                ], 404);
            }

            return response()->json($seminaires, 200);
        } catch (\Exception $e) {
            return SeminaireJIPaysExceptionHandler::handle($e);
        }
    }

    public function store(SeminaireJIPaysRequest $request)
    {
        try {
            $data = $request->validated();

            // Gérer l'upload du fichier PDF
            if ($request->hasFile('fichier_pdf')) {
                $path = $request->file('fichier_pdf')->store('pdfs', 'public');
                $data['fichier_pdf'] = $path;
            }

            $seminaire = SeminaireJIPays::create($data);

            return response()->json([
                'message' => 'Séminaire créé avec succès',
                'data' => $seminaire
            ], 201);
        } catch (\Exception $e) {
            return SeminaireJIPaysExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $seminaire = SeminaireJIPays::findOrFail($id);
            return response()->json($seminaire, 200);
        } catch (\Exception $e) {
            return SeminaireJIPaysExceptionHandler::handle($e);
        }
    }

   
    public function update(SeminaireJIPaysRequest $request, $id)
    {
        try {
            // Log des informations de débogage
            \Log::info('Mise à jour du séminaire', [
                'id' => $id,
                'content_type' => $request->header('Content-Type'),
                'has_file' => $request->hasFile('fichier_pdf'),
                'method' => $request->method(),
                'all_data' => $request->all()
            ]);
    
            $seminaire = SeminaireJIPays::findOrFail($id);
            
            // Valider manuellement les données
            $validator = Validator::make($request->all(), [
                'intitule' => 'sometimes|required|string|max:255',
                'pays_id' => 'sometimes|required',
                'fichier_pdf' => 'sometimes|file|mimes:pdf|max:10240', // 10MB
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Récupérer toutes les données validées
            $data = $request->all();
            
            // Traitement spécial pour le fichier PDF
            if ($request->hasFile('fichier_pdf')) {
                \Log::info('Fichier PDF détecté dans la requête', [
                    'original_name' => $request->file('fichier_pdf')->getClientOriginalName()
                ]);
                
                // Supprimer l'ancien fichier si existant
                if ($seminaire->fichier_pdf && Storage::disk('public')->exists($seminaire->fichier_pdf)) {
                    Storage::disk('public')->delete($seminaire->fichier_pdf);
                    \Log::info('Ancien fichier supprimé');
                }
                
                // Utiliser un nom unique avec timestamp
                $fileName = pathinfo($request->file('fichier_pdf')->getClientOriginalName(), PATHINFO_FILENAME);
                $timestamp = time();
                $uniqueName = $fileName . '_' . $timestamp . '.pdf';
                
                // Stocker le nouveau fichier
                $path = $request->file('fichier_pdf')->storeAs('pdfs', $uniqueName, 'public');
                $data['fichier_pdf'] = $path;
                
                \Log::info('Nouveau fichier enregistré', ['path' => $path]);
            } else {
                // Si pas de fichier dans la requête, ne pas modifier le champ
                unset($data['fichier_pdf']);
                \Log::info('Pas de fichier PDF dans la requête, conservation de l\'ancien');
            }
            
            // Mise à jour du séminaire
            $seminaire->update($data);
            
            return response()->json([
                'message' => 'Séminaire mis à jour avec succès',
                'data' => $seminaire->fresh(),
                'file_updated' => $request->hasFile('fichier_pdf')
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la mise à jour', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Erreur lors de la mise à jour du séminaire',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $seminaire = SeminaireJIPays::findOrFail($id);

            // Supprimer le fichier PDF s'il existe
            if ($seminaire->fichier_pdf) {
                Storage::disk('public')->delete($seminaire->fichier_pdf);
            }

            $seminaire->delete();

            return response()->json([
                'message' => "Séminaire avec l'ID {$id} a été supprimé avec succès"
            ], 200);
        } catch (\Exception $e) {
            return SeminaireJIPaysExceptionHandler::handle($e);
        }
    }
}