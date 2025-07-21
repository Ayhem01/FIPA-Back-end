<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CTE;
use App\Http\Requests\CTERequest;
use App\Exceptions\CTEExceptionHandler;

class CTEController extends Controller
{
    public function index()
    {
        try {
            $ctes = CTE::with(['initiateur', 'pays', 'secteur'])->get();

            if ($ctes->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun CTE trouvé.'
                ], 404);
            }

            return response()->json($ctes, 200);
        } catch (\Exception $e) {
            return CTEExceptionHandler::handle($e);
        }
    }

    public function store(CTERequest $request)
    {
        try {
            $data = $request->validated();
            $cte = CTE::create($data);

            return response()->json([
                'message' => 'CTE créé avec succès',
                'data' => $cte
            ], 201);
        } catch (\Exception $e) {
            return CTEExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $cte = CTE::with(['initiateur', 'pays', 'secteur'])->findOrFail($id);
            return response()->json($cte, 200);
        } catch (\Exception $e) {
            return CTEExceptionHandler::handle($e);
        }
    }

    public function update(CTERequest $request, $id)
    {
        try {
            $cte = CTE::findOrFail($id);
            $cte->update($request->all());

            return response()->json([
                'message' => 'CTE mis à jour avec succès',
                'data' => $cte
            ], 200);
        } catch (\Exception $e) {
            return CTEExceptionHandler::handle($e);
        }
    }

    public function destroy($id)
    {
        try {
            $cte = CTE::findOrFail($id);
            $cte->delete();

            return response()->json([
                'message' => "CTE avec l'ID {$id} a été supprimé avec succès"
            ], 200);
        } catch (\Exception $e) {
            return CTEExceptionHandler::handle($e);
        }
    }
}