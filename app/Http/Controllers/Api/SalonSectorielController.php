<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalonSectoriels;
use App\Http\Requests\SalonSectorielRequest;
use App\Exceptions\SalonSectorielExceptionHandler;

class SalonSectorielController extends Controller
{
    public function index()
    {
        try {
            $salons = SalonSectoriels::all();

            if ($salons->isEmpty()) {
                return response()->json([
                    'message' => 'Aucun salon sectoriel trouvé.'
                ], 404);
            }

            return response()->json($salons, 200);
        } catch (\Exception $e) {
            return SalonSectorielExceptionHandler::handle($e);
        }
    }

    public function store(SalonSectorielRequest $request)
    {
        try {
            $data = $request->validated();

            $salon = SalonSectoriels::create($data);

            return response()->json([
                'message' => 'Salon sectoriel créé avec succès.',
                'data' => $salon
            ], 201);
        } catch (\Exception $e) {
            return SalonSectorielExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $salon = SalonSectoriels::findOrFail($id);

            return response()->json($salon, 200);
        } catch (\Exception $e) {
            return SalonSectorielExceptionHandler::handle($e);
        }
    }

    public function update(SalonSectorielRequest $request, $id)
    {
        try {
            $salon = SalonSectoriels::findOrFail($id);

            $data = $request->validated();
            $salon->fill($data)->save();

            return response()->json([
                'message' => 'Salon sectoriel mis à jour avec succès.',
                'data' => $salon
            ], 200);
        } catch (\Exception $e) {
            return SalonSectorielExceptionHandler::handle($e);
        }
    }

    public function destroy($id)
{
    try {
        $salon = SalonSectoriels::findOrFail($id);

        $salon->delete();

        return response()->json([
            'message' => "Salon sectoriel avec l'ID {$id} a été supprimé avec succès."
        ], 200);
    } catch (\Exception $e) {
        return SalonSectorielExceptionHandler::handle($e);
    }
}
    
}