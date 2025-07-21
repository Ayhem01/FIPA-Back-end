<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Nationalite;
use App\Http\Requests\NationaliteRequest;
use App\Exceptions\NationaliteExceptionHandler;

class NationaliteController extends Controller
{
    public function index()
    {
        try {
            $nationalites = Nationalite::all();

            if ($nationalites->isEmpty()) {
                return response()->json([
                    'message' => 'Aucune nationalité trouvée.'
                ], 404);
            }

            return response()->json($nationalites, 200);
        } catch (\Exception $e) {
            return NationaliteExceptionHandler::handle($e);
        }
    }

    public function store(NationaliteRequest $request)
    {
        try {
            $data = $request->validated();
            $nationalite = Nationalite::create($data);

            return response()->json([
                'message' => 'Nationalité créée avec succès',
                'data' => $nationalite
            ], 201);
        } catch (\Exception $e) {
            return NationaliteExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $nationalite = Nationalite::findOrFail($id);
            return response()->json($nationalite, 200);
        } catch (\Exception $e) {
            return NationaliteExceptionHandler::handle($e);
        }
    }

    public function update(NationaliteRequest $request, $id)
    {
        try {
            $nationalite = Nationalite::findOrFail($id);
            $nationalite->update($request->validated());

            return response()->json([
                'message' => 'Nationalité mise à jour avec succès',
                'data' => $nationalite
            ], 200);
        } catch (\Exception $e) {
            return NationaliteExceptionHandler::handle($e);
        }
    }

    public function destroy($id)
    {
        try {
            $nationalite = Nationalite::findOrFail($id);
            $name = $nationalite->name;
            $nationalite->delete();

            return response()->json([
                'message' => "Nationalité {$name} a été supprimée avec succès"
            ], 200);
        } catch (\Exception $e) {
            return NationaliteExceptionHandler::handle($e);
        }
    }
}