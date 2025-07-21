<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResponsableFipa;
use App\Http\Requests\ResponsableFipaRequest;
use App\Exceptions\ResponsableFipaExceptionHandler;

class ResponsableFipaController extends Controller
{
    public function index()
    {
        try {
            $responsables = ResponsableFipa::all();

            if ($responsables->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun responsable FIPA trouvé.'
                ], 404);
            }

            return response()->json($responsables, 200);
        } catch (\Exception $e) {
            return ResponsableFipaExceptionHandler::handle($e);
        }
    }

    public function store(ResponsableFipaRequest $request)
    {
        try {
            $data = $request->validated();
            $responsable = ResponsableFipa::create($data);

            return response()->json([
                'message' => 'Responsable FIPA créé avec succès',
                'data' => $responsable
            ], 201);
        } catch (\Exception $e) {
            return ResponsableFipaExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $responsable = ResponsableFipa::findOrFail($id);
            return response()->json($responsable, 200);
        } catch (\Exception $e) {
            return ResponsableFipaExceptionHandler::handle($e);
        }
    }

    public function update(ResponsableFipaRequest $request, $id)
    {
        try {
            $responsable = ResponsableFipa::findOrFail($id);
            $responsable->update($request->validated());

            return response()->json([
                'message' => 'Responsable FIPA mis à jour avec succès',
                'data' => $responsable
            ], 200);
        } catch (\Exception $e) {
            return ResponsableFipaExceptionHandler::handle($e);
        }
    }

    public function destroy($id)
    {
        try {
            $responsable = ResponsableFipa::findOrFail($id);
            $name = $responsable->nom;
            $responsable->delete();

            return response()->json([
                'message' => "Responsable FIPA {$name} a été supprimé avec succès"
            ], 200);
        } catch (\Exception $e) {
            return ResponsableFipaExceptionHandler::handle($e);
        }
    }
}