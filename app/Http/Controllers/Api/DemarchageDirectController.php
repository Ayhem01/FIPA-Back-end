<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DemarchageDirect;
use App\Http\Requests\DemarchageDirectRequest;
use App\Exceptions\DemarchageDirectExceptionHandler;

class DemarchageDirectController extends Controller
{
    public function index()
    {
        try {
            $demarchages = DemarchageDirect::all();

            if ($demarchages->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun démarchage direct trouvé.'
                ], 404);
            }

            return response()->json($demarchages, 200);
        } catch (\Exception $e) {
            return DemarchageDirectExceptionHandler::handle($e);
        }
    }

    public function store(DemarchageDirectRequest $request)
    {
        try {
            $data = $request->validated();
            $demarchage = DemarchageDirect::create($data);

            return response()->json([
                'message' => 'Démarchage direct créé avec succès.',
                'data' => $demarchage
            ], 201);
        } catch (\Exception $e) {
            return DemarchageDirectExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $demarchage = DemarchageDirect::findOrFail($id);

            return response()->json($demarchage, 200);
        } catch (\Exception $e) {
            return DemarchageDirectExceptionHandler::handle($e);
        }
    }

    public function update(DemarchageDirectRequest $request, $id)
    {
        try {
            $demarchage = DemarchageDirect::findOrFail($id);
            $data = $request->validated();
            $demarchage->update($data);

            return response()->json([
                'message' => 'Démarchage direct mis à jour avec succès.',
                'data' => $demarchage
            ], 200);
        } catch (\Exception $e) {
            return DemarchageDirectExceptionHandler::handle($e);
        }
    }

    public function destroy($id)
    {
        try {
            $demarchage = DemarchageDirect::findOrFail($id);
            $demarchage->delete();

            return response()->json([
                'message' => "Démarchage direct avec l'ID {$id} a été supprimé avec succès."
            ], 200);
        } catch (\Exception $e) {
            return DemarchageDirectExceptionHandler::handle($e);
        }
    }
}