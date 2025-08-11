<?php

namespace App\Http\Controllers\Api;
use App\Exceptions\MediaExceptionHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\MediaRequest;
use App\Models\Media;

class MediaController extends Controller
{
    public function index()
    {
        return Media::all();
    }
    public function store(MediaRequest $request)
    {
        try {
            $data = $request->validated();
            $media = Media::create($data);

            return response()->json([
                'message' => 'Media created successfully',
                'data' => $media
            ], 201);
        } catch (\Exception $e) {
            return MediaExceptionHandler::handle($e);
        }
    }
    
    

    public function show($id)
    {
        try {
            $media = Media::findOrFail($id);
            return response()->json($media);
        } catch (\Exception $e) {
            return MediaExceptionHandler::handle($e);
        }
    }

    public function update(MediaRequest $request, $id)
    {
        try {
        $media = Media::findOrFail($id);
        $media->update($request->all());
        return response()->json([
            'message' => 'Media updated successfully',
            'data' => $media
        ], 200);
    } catch (\Exception $e) {
        return MediaExceptionHandler::handle($e);
    }
    }

    public function destroy($id)
{
    try {
        $media = Media::findOrFail($id);
        $media->delete(); 
        return response()->json([
            'message' => "Media avec l'ID {$id} a été supprimé avec succès"
        ], 200);
    } catch (\Exception $e) {
        return MediaExceptionHandler::handle($e); 
    }
}
}
