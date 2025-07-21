<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pays;
use App\Http\Requests\PaysRequest;
use App\Exceptions\PaysExceptionHandler;

class PaysController extends Controller
{
    public function index()
    {
        try {
            $pays = Pays::all();

            if ($pays->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun pays trouvé.'
                ], 404);
            }

            return response()->json($pays, 200);
        } catch (\Exception $e) {
            return PaysExceptionHandler::handle($e);
        }
    }

    public function store(PaysRequest $request)
    {
        try {
            $data = $request->validated();
            $pays = Pays::create($data);

            return response()->json([
                'message' => 'Pays créé avec succès',
                'data' => $pays
            ], 201);
        } catch (\Exception $e) {
            return PaysExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $pays = Pays::findOrFail($id);
            return response()->json($pays, 200);
        } catch (\Exception $e) {
            return PaysExceptionHandler::handle($e);
        }
    }

    public function update(PaysRequest $request, $id)
    {
        try {
            $pays = Pays::findOrFail($id);
            $pays->update($request->all());

            return response()->json([
                'message' => 'Pays mis à jour avec succès',
                'data' => $pays
            ], 200);
        } catch (\Exception $e) {
            return PaysExceptionHandler::handle($e);
        }
    }

    public function destroy($id)
    {
        try {
            $pays = Pays::findOrFail($id);
            $name = $pays->name_pays;
            $pays->delete();

            return response()->json([
                'message' => "Pays avec le nom '{$name}' a été supprimé avec succès"
            ], 200);
        } catch (\Exception $e) {
            return PaysExceptionHandler::handle($e);
        }
    }
}