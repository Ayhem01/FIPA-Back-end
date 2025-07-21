<?php

namespace App\Exceptions\SuivieProjet;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Illuminate\Validation\ValidationException;

class StatsExceptionHandler
{
    /**
     * Gère les exceptions pour les statistiques
     *
     * @param Exception $exception
     * @return \Illuminate\Http\JsonResponse
     */
    public static function handle(Exception $exception)
    {
        if ($exception instanceof ValidationException) {
            return response()->json([
                'message' => 'Les données fournies sont invalides.',
                'errors' => $exception->errors(), 
            ], 422);
        }
        
        if ($exception instanceof ModelNotFoundException) {
            return response()->json(['message' => 'Ressource statistique non trouvée'], 404);
        }
        
        return response()->json([
            'message' => 'Une erreur est survenue lors de la récupération des statistiques',
            'error' => $exception->getMessage()
        ], 500);
    }
}