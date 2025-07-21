<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResponsableBureauMedia;
use App\Http\Requests\ResponsableBureauMediaRequest;
use App\Exceptions\ResponsableBureauMediaExceptionHandler;

class ResponsableBureauMediaController extends Controller
{
    public function index()
    {
        try {
            $responsableBureauMedias = ResponsableBureauMedia::all();
    
            if ($responsableBureauMedias->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun responsable bureau media trouvé.'
                ], 404);
            }
    
            return response()->json($responsableBureauMedias, 200);
        } catch (\Exception $e) {
            return ResponsableBureauMediaExceptionHandler::handle($e);
        }
    }
    public function store(ResponsableBureauMediaRequest $request)
    {
        try {
            $data = $request->validated();
            $responsableBureauMedia = ResponsableBureauMedia::create($data);

            return response()->json([
                'message' => 'ResponsableBureauMedia created successfully',
                'data' => $responsableBureauMedia
            ], 201);
        } catch (\Exception $e) {
            return ResponsableBureauMediaExceptionHandler::handle($e);
        }
    }
    public function show($id)
    {
        try {
            $responsableBureauMedia = ResponsableBureauMedia::findOrFail($id);
            return response()->json($responsableBureauMedia, 200);
        } catch (\Exception $e) {
            return ResponsableBureauMediaExceptionHandler::handle($e);
        }
    }
    public function update(ResponsableBureauMediaRequest $request, $id)
    {
        try {
            $responsableBureauMedia = ResponsableBureauMedia::findOrFail($id);
            $responsableBureauMedia->update($request->all());

            return response()->json([
                'message' => 'ResponsableBureauMedia updated successfully',
                'data' => $responsableBureauMedia
            ], 200);
        } catch (\Exception $e) {
            return ResponsableBureauMediaExceptionHandler::handle($e);
        }
    }
    public function destroy($id)
    {
        try {
            $responsableBureauMedia = ResponsableBureauMedia::findOrFail($id);
            $name = $responsableBureauMedia->name;
            $responsableBureauMedia->delete();

            return response()->json([
                'message' => "Responsable Bureau Media avec le nom {$name} a été supprimé avec succès"
            ], 200);
        } catch (\Exception $e) {
            return ResponsableBureauMediaExceptionHandler::handle($e);
        }
    }
}
