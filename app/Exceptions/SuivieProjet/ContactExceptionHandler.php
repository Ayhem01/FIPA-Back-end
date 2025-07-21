<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class ContactExceptionHandler
{
    /**
     * Handle the exception for contact related operations
     *
     * @param \Exception $exception
     * @return \Illuminate\Http\JsonResponse
     */
    public static function handle(\Exception $exception)
    {
        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Contact non trouvé'
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
                    'message' => 'Impossible de traiter cette opération car le contact est lié à d\'autres éléments'
                ], 409);
            }
        }

        // Log l'exception
        \Log::error('Erreur Contact : ' . $exception->getMessage(), [
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