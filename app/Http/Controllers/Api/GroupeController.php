<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Groupe;
use App\Http\Requests\GroupeRequest;
use App\Exceptions\GroupeExceptionHandler;

class GroupeController extends Controller
{
    public function index()
    {
        try {
            $groupes = Groupe::all();

            if ($groupes->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun groupe trouvé.'
                ], 404);
            }

            return response()->json($groupes, 200);
        } catch (\Exception $e) {
            return GroupeExceptionHandler::handle($e);
        }
    }

    public function store(GroupeRequest $request)
    {
        try {
            $data = $request->validated();
            $groupe = Groupe::create($data);

            return response()->json([
                'message' => 'Groupe créé avec succès.',
                'data' => $groupe
            ], 201);
        } catch (\Exception $e) {
            return GroupeExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $groupe = Groupe::findOrFail($id);

            return response()->json($groupe, 200);
        } catch (\Exception $e) {
            return GroupeExceptionHandler::handle($e);
        }
    }

    public function update(GroupeRequest $request, $id)
    {
        try {
            $groupe = Groupe::findOrFail($id);
            $data = $request->validated();
            $groupe->fill($data)->save();

            return response()->json([
                'message' => 'Groupe mis à jour avec succès.',
                'data' => $groupe
            ], 200);
        } catch (\Exception $e) {
            return GroupeExceptionHandler::handle($e);
        }
    }

    public function destroy($id)
    {
        try {
            $groupe = Groupe::findOrFail($id);
            $groupe->delete();

            return response()->json([
                'message' => "Groupe avec l'ID {$id} a été supprimé avec succès."
            ], 200);
        } catch (\Exception $e) {
            return GroupeExceptionHandler::handle($e);
        }
    }
}