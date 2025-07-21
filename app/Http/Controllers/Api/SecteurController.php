<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Secteur;
use App\Http\Requests\SecteurRequest;
use App\Exceptions\SecteurExceptionHandler;

class SecteurController extends Controller
{
    public function index()
    {
        try {
            $secteurs = Secteur::all();

            if ($secteurs->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun secteur trouvé.'
                ], 404);
            }

            return response()->json($secteurs, 200);
        } catch (\Exception $e) {
            return SecteurExceptionHandler::handle($e);
        }
    }

    public function store(SecteurRequest $request)
    {
        try {
            $data = $request->validated();
            $secteur = Secteur::create($data);

            return response()->json([
                'message' => 'Secteur créé avec succès',
                'data' => $secteur
            ], 201);
        } catch (\Exception $e) {
            return SecteurExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $secteur = Secteur::findOrFail($id);
            return response()->json($secteur, 200);
        } catch (\Exception $e) {
            return SecteurExceptionHandler::handle($e);
        }
    }

    public function update(SecteurRequest $request, $id)
    {
        try {
            $secteur = Secteur::findOrFail($id);
            $secteur->update($request->all());

            return response()->json([
                'message' => 'Secteur mis à jour avec succès',
                'data' => $secteur
            ], 200);
        } catch (\Exception $e) {
            return SecteurExceptionHandler::handle($e);
        }
    }

    public function destroy($id)
    {
        try {
            $secteur = Secteur::findOrFail($id);
            $name = $secteur->name;
            $secteur->delete();

            return response()->json([
                'message' => "Secteur avec le nom '{$name}' a été supprimé avec succès"
            ], 200);
        } catch (\Exception $e) {
            return SecteurExceptionHandler::handle($e);
        }
    }
}