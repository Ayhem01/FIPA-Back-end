<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Binome;
use App\Http\Requests\BinomeRequest;
use App\Exceptions\BinomeExceptionHandler;
use App\Models\Binomes;

class BinomeController extends Controller
{
    public function index()
    {
        try {
            $binomes = Binomes::all();

            if ($binomes->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun binôme trouvé.'
                ], 404);
            }

            return response()->json($binomes, 200);
        } catch (\Exception $e) {
            return BinomeExceptionHandler::handle($e);
        }
    }

    public function store(BinomeRequest $request)
    {
        try {
            $data = $request->validated();
            $binome = Binomes::create($data);

            return response()->json([
                'message' => 'Binôme créé avec succès',
                'data' => $binome
            ], 201);
        } catch (\Exception $e) {
            return BinomeExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $binome = Binomes::findOrFail($id);
            return response()->json($binome, 200);
        } catch (\Exception $e) {
            return BinomeExceptionHandler::handle($e);
        }
    }

    public function update(BinomeRequest $request, $id)
    {
        try {
            $binome = Binomes::findOrFail($id);
            $binome->update($request->validated());

            return response()->json([
                'message' => 'Binôme mis à jour avec succès',
                'data' => $binome
            ], 200);
        } catch (\Exception $e) {
            return BinomeExceptionHandler::handle($e);
        }
    }

    public function destroy($id)
    {
        try {
            $binome = Binomes::findOrFail($id);
            $binome->delete();

            return response()->json([
                'message' => "Binôme avec l'ID {$id} a été supprimé avec succès"
            ], 200);
        } catch (\Exception $e) {
            return BinomeExceptionHandler::handle($e);
        }
    }
}