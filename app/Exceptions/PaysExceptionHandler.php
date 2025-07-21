<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Exception;

class PaysExceptionHandler
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
            return response()->json([
                'message' => 'Pays non trouvé'
            ], 404);
        }
        return response()->json([
            'message' => 'Une erreur est survenue',
            'error' => $exception->getMessage()
        ], 500);
    }
}