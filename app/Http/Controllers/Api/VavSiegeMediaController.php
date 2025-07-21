<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VavSiegeMedia;
use App\Http\Requests\VavSiegeMediaRequest;
use App\Exceptions\VavSiegeMediaExceptionHandler;

class VavSiegeMediaController extends Controller
{
    public function index()
    {
        try {
            $vavSiegeMedias = VavSiegeMedia::all();

            if ($vavSiegeMedias->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun VavSiegeMedia trouvé.'
                ], 404);
            }

            return response()->json($vavSiegeMedias, 200);
        } catch (\Exception $e) {
            return VavSiegeMediaExceptionHandler::handle($e);
        }
    }
    public function store(VavSiegeMediaRequest $request)
    {
        try {
            $data = $request->validated();
            $vavSiegeMedia = VavSiegeMedia::create($data);

            return response()->json([
                'message' => 'VavSiegeMedia créé avec succès',
                'data' => $vavSiegeMedia
            ], 201);
        } catch (\Exception $e) {
            return VavSiegeMediaExceptionHandler::handle($e);
        }
    }
    public function show($id)
    {
        try {
            $vavSiegeMedia = VavSiegeMedia::findOrFail($id);
            return response()->json($vavSiegeMedia, 200);
        } catch (\Exception $e) {
            return VavSiegeMediaExceptionHandler::handle($e);
        }
    }
    public function update(VavSiegeMediaRequest $request, $id)
    {
        try {
            $vavSiegeMedia = VavSiegeMedia::findOrFail($id);
            $vavSiegeMedia->update($request->validated());

            return response()->json([
                'message' => 'VavSiegeMedia mis à jour avec succès',
                'data' => $vavSiegeMedia
            ], 200);
        } catch (\Exception $e) {
            return VavSiegeMediaExceptionHandler::handle($e);
        }
    }
    public function destroy($id)
    {
        try {
            $vavSiegeMedia = VavSiegeMedia::findOrFail($id);
            $name = $vavSiegeMedia->name; // Récupérer le nom avant suppression
            $vavSiegeMedia->delete();

            return response()->json([
                'message' => "Le Vav Siege Media avec le nom '{$name}' a été supprimé avec succès"
            ], 200);
        } catch (\Exception $e) {
            return VavSiegeMediaExceptionHandler::handle($e);
        }
    }
}






