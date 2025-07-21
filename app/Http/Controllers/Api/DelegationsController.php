<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delegations;
use App\Http\Requests\DelegationsRequest;
use App\Exceptions\DelegationExceptionHandler;
class DelegationsController extends Controller
{
    public function index()
    {
        try {
            $delegations = Delegations::with(['responsableFipa', 'groupe', 'initiateur', 'secteur', 'nationalite'])->get();

            if ($delegations->isEmpty()) {
                return response()->json([
                    'message' => 'Aucune délégation trouvée.'
                ], 404);
            }

            return response()->json($delegations, 200);
        } catch (\Exception $e) {
            return DelegationExceptionHandler::handle($e);
        }
    }

    public function store(DelegationsRequest $request)
    {
        try {
            $data = $request->validated();
            $delegation = Delegations::create($data);

            return response()->json([
                'message' => 'Délégation créée avec succès',
                'data' => $delegation
            ], 201);
        } catch (\Exception $e) {
            return DelegationExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $delegation = Delegations::with(['responsableFipa', 'groupe', 'initiateur', 'secteur', 'nationalite'])->findOrFail($id);
            return response()->json($delegation, 200);
        } catch (\Exception $e) {
            return DelegationExceptionHandler::handle($e);
        }
    }

    public function update(DelegationsRequest $request, $id)
    {
        try {
            $delegation = Delegations::findOrFail($id);
            $delegation->update($request->validated());

            return response()->json([
                'message' => 'Délégation mise à jour avec succès',
                'data' => $delegation
            ], 200);
        } catch (\Exception $e) {
            return DelegationExceptionHandler::handle($e);
        }
    }

    public function destroy($id)
    {
        try {
            $delegation = Delegations::findOrFail($id);
            $delegation->delete();

            return response()->json([
                'message' => "Délégation avec l'ID {$id} a été supprimée avec succès"
            ], 200);
        } catch (\Exception $e) {
            return DelegationExceptionHandler::handle($e);
        }
    }
}