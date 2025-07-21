<?php

namespace App\Exceptions\SuivieProjet;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Illuminate\Validation\ValidationException;

class ProjectPipelineTypeExceptionHandler
{
    public static function handle(Exception $exception)
    {
        if ($exception instanceof ValidationException) {
            return response()->json([
                'message' => 'Les données fournies sont invalides.',
                'errors' => $exception->errors(), 
            ], 422);
        }
        if ($exception instanceof ModelNotFoundException) {
            return response()->json(['message' => 'Type de pipeline non trouvé'], 404);
        }
        return response()->json([
            'message' => 'Une erreur est survenue',
            'error' => $exception->getMessage()
        ], 500);
    }
}