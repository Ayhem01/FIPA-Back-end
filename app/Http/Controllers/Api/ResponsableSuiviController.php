<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResponsableSuivi;
use App\Http\Requests\ResponsableSuiviRequest;
use App\Exceptions\ResponsableSuiviExceptionHandler;

class ResponsableSuiviController extends Controller
{
    public function index()
    {
        try {
            $responsables = ResponsableSuivi::all();

            if ($responsables->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun responsable de suivi trouvé.'
                ], 404);
            }

            return response()->json($responsables, 200);
        } catch (\Exception $e) {
            return ResponsableSuiviExceptionHandler::handle($e);
        }
    }

    public function store(ResponsableSuiviRequest $request)
    {
        try {
            $data = $request->validated();
            $responsable = ResponsableSuivi::create($data);

            return response()->json([
                'message' => 'Responsable de suivi créé avec succès.',
                'data' => $responsable
            ], 201);
        } catch (\Exception $e) {
            return ResponsableSuiviExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $responsable = ResponsableSuivi::findOrFail($id);

            return response()->json($responsable, 200);
        } catch (\Exception $e) {
            return ResponsableSuiviExceptionHandler::handle($e);
        }
    }

    public function update(ResponsableSuiviRequest $request, $id)
    {
        try {
            $responsable = ResponsableSuivi::findOrFail($id);
            $responsable->update($request->validated());

            return response()->json([
                'message' => 'Responsable de suivi mis à jour avec succès.',
                'data' => $responsable
            ], 200);
        } catch (\Exception $e) {
            return ResponsableSuiviExceptionHandler::handle($e);
        }
    }

    public function destroy($id)
    {
        try {
            $responsable = ResponsableSuivi::findOrFail($id);
            $responsable->delete();

            return response()->json([
                'message' => "Responsable de suivi avec l'ID {$id} a été supprimé avec succès."
            ], 200);
        } catch (\Exception $e) {
            return ResponsableSuiviExceptionHandler::handle($e);
        }
    }
}