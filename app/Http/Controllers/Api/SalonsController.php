<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Salons;
use App\Http\Requests\SalonsRequest;
use App\Exceptions\SalonsExceptionHandler;

class SalonsController extends Controller
{
    public function index()
    {
        try {
            $salons = Salons::all();

            if ($salons->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun salon trouvé.'
                ], 404);
            }

            return response()->json($salons, 200);
        } catch (\Exception $e) {
            return SalonsExceptionHandler::handle($e);
        }
    }

    public function store(SalonsRequest $request)
    {
        try {
            $data = $request->validated();
            $salon = Salons::create($data);

            return response()->json([
                'message' => 'Salon créé avec succès',
                'data' => $salon
            ], 201);
        } catch (\Exception $e) {
            return SalonsExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $salon = Salons::findOrFail($id);
            return response()->json($salon, 200);
        } catch (\Exception $e) {
            return SalonsExceptionHandler::handle($e);
        }
    }

    public function update(SalonsRequest $request, $id)
    {
        try {
            $salon = Salons::findOrFail($id);
            $salon->update($request->validated());

            return response()->json([
                'message' => 'Salon mis à jour avec succès',
                'data' => $salon
            ], 200);
        } catch (\Exception $e) {
            return SalonsExceptionHandler::handle($e);
        }
    }

    public function destroy($id)
    {
        try {
            $salon = Salons::findOrFail($id);
            $salon->delete();

            return response()->json([
                'message' => "Salon avec l'ID {$id} a été supprimé avec succès"
            ], 200);
        } catch (\Exception $e) {
            return SalonsExceptionHandler::handle($e);
        }
    }
}