<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\InitiateurRequest;
use App\Exceptions\InitiateurExceptionHandler;
use App\Models\Initiateurs;

class InitiateurController extends Controller
{
    public function index()
    {
        try {
            $initiateurs = Initiateurs::all();

            if ($initiateurs->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun Initiateur trouvé.'
                ], 404);
            }

            return response()->json($initiateurs, 200);
        } catch (\Exception $e) {
            return InitiateurExceptionHandler::handle($e);
        }
    }
    public function store(InitiateurRequest $request)
    {
        try {
            $data = $request->validated();
            $initiateur = Initiateurs::create($data);

            return response()->json([
                'message' => 'Initiateur créé avec succès',
                'data' => $initiateur
            ], 201);
        } catch (\Exception $e) {
            return InitiateurExceptionHandler::handle($e);
        }
    }
    public function show($id)
    {
        try {
            $initiateur = Initiateurs::findOrFail($id);
            return response()->json($initiateur, 200);
        } catch (\Exception $e) {
            return InitiateurExceptionHandler::handle($e);
        }
    }
    public function update(InitiateurRequest $request, $id)
    {
        try {
            $initiateur = Initiateurs::findOrFail($id);
            $initiateur->update($request->validated());

            return response()->json([
                'message' => 'Initiateur mis à jour avec succès',
                'data' => $initiateur
            ], 200);
        } catch (\Exception $e) {
            return InitiateurExceptionHandler::handle($e);
        }
    }
    public function destroy($id)
    {
        try {
            $initiateur = Initiateurs::findOrFail($id);
            $name = $initiateur->name; 
            $initiateur->delete();

            return response()->json([
                'message' => "L'Initiateur avec le nom '{$name}' a été supprimé avec succès"
            ], 200);
        } catch (\Exception $e) {
            return InitiateurExceptionHandler::handle($e);
        }
    }
    

}



