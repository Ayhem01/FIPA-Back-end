<?php

namespace App\Exceptions\SuivieProjet;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class ActionExceptionHandler
{
    /**
     * Handle the exception for action related operations
     *
     * @param \Exception $exception
     * @return \Illuminate\Http\JsonResponse
     */
    public static function handle(\Exception $exception)
    {
        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Action non trouvée'
            ], 404);
        }

        if ($exception instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $exception->validator->errors()
            ], 422);
        }

        if ($exception instanceof QueryException) {
            // Contrainte de clé étrangère violée
            if ($exception->getCode() == 23000) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de traiter cette opération car l\'action est liée à d\'autres éléments'
                ], 409);
            }
        }

        // Log l'exception
        \Log::error('Erreur Action : ' . $exception->getMessage(), [
            'exception' => $exception,
            'trace' => $exception->getTraceAsString()
        ]);

        // Réponse par défaut pour toutes les autres exceptions
        return response()->json([
            'success' => false,
            'message' => 'Une erreur est survenue lors du traitement de votre demande',
            'error' => config('app.debug') ? $exception->getMessage() : null
        ], 500);
    }
}