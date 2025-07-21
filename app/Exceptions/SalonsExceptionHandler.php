<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Exception;

class SalonsExceptionHandler
{
    /**
     * Gérer les exceptions pour SalonsController.
     *
     * @param Exception $exception
     * @return \Illuminate\Http\JsonResponse
     */
    public static function handle(Exception $exception)
    {
        // Gérer les exceptions de validation
        if ($exception instanceof ValidationException) {
            return response()->json([
                'message' => 'Les données fournies sont invalides.',
                'errors' => $exception->errors(),
            ], 422);
        }

        // Gérer les exceptions de modèle non trouvé
        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'message' => 'Salon non trouvé'
            ], 404);
        }

        // Gérer toute autre exception
        return response()->json([
            'message' => 'Une erreur est survenue',
            'error' => $exception->getMessage()
        ], 500);
    }
}