<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SeminairesJISecteur;
use App\Http\Requests\SeminaireJISecteurRequest;
use App\Exceptions\SeminaireJISecteurExceptionHandler;
use Illuminate\Support\Facades\Storage;

class SeminaireJISecteurController extends Controller
{
    public function index()
    {
        try {
            $seminaires = SeminairesJISecteur::all();

            if ($seminaires->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun séminaire JI Secteur trouvé.'
                ], 404);
            }

            return response()->json($seminaires, 200);
        } catch (\Exception $e) {
            return SeminaireJISecteurExceptionHandler::handle($e);
        }
    }

    public function store(SeminaireJISecteurRequest $request)
    {
        try {
            $data = $request->validated();
            if ($request->hasFile('fichier_presence')) {
                $file = $request->file('fichier_presence');
    
                $filePath = $file->store('pdfs', 'public');
                
    
                $data['fichier_presence'] = $filePath;
            }
            $seminaire = SeminairesJISecteur::create($data);

            return response()->json([
                'message' => 'Séminaire JI Secteur créé avec succès.',
                'data' => $seminaire
            ], 201);
        } catch (\Exception $e) {
            return SeminaireJISecteurExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $seminaire = SeminairesJISecteur::findOrFail($id);

            return response()->json($seminaire, 200);
        } catch (\Exception $e) {
            return SeminaireJISecteurExceptionHandler::handle($e);
        }
    }

   
public function update(SeminaireJISecteurRequest $request, $id)
{
    try {
        $seminaire = SeminairesJISecteur::findOrFail($id);
        $data = $request->validated();

        // Gérer l'upload du fichier PDF
        if ($request->hasFile('fichier_presence')) {
            // Supprimer l'ancien fichier s'il existe
            if ($seminaire->fichier_presence) {
                Storage::disk('public')->delete($seminaire->fichier_presence);
            }

            // Sauvegarder le nouveau fichier PDF
            $path = $request->file('fichier_presence')->store('pdfs', 'public');
            $data['fichier_presence'] = $path;
        }
        $seminaire->fill($data)->save();

        return response()->json([
            'message' => 'Séminaire JI Secteur mis à jour avec succès.',
            'data' => $seminaire
        ], 200);
    } catch (\Exception $e) {
        return SeminaireJISecteurExceptionHandler::handle($e);
    }
}

public function destroy($id)
{
    try {
        $seminaire = SeminairesJISecteur::findOrFail($id);

        // Supprimer le fichier PDF associé s'il existe
        if ($seminaire->fichier_presence) {
            Storage::disk('public')->delete($seminaire->fichier_presence);
        }

        // Supprimer le séminaire
        $seminaire->delete();

        return response()->json([
            'message' => "Séminaire JI Secteur avec l'ID {$id} a été supprimé avec succès."
        ], 200);
    } catch (\Exception $e) {
        return SeminaireJISecteurExceptionHandler::handle($e);
    }
}
}