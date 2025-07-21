<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Exception;

class SalonSectorielExceptionHandler
{
    public static function handle(Exception $exception)
    {
        // Gestion des erreurs de validation
        if ($exception instanceof ValidationException) {
            return response()->json([
                'message' => 'Les données fournies sont invalides.',
                'errors' => $exception->errors(),
            ], 422);
        }

        // Gestion des erreurs de modèle non trouvé
        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'message' => 'Salon sectoriel non trouvé.',
            ], 404);
        }

        // Gestion des erreurs de requête SQL
        if ($exception instanceof QueryException) {
            return response()->json([
                'message' => 'Une erreur de base de données est survenue.',
                'error' => $exception->getMessage(),
            ], 500);
        }

        // Gestion des erreurs HTTP spécifiques (404, 405, etc.)
        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'message' => 'La ressource demandée est introuvable.',
            ], 404);
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'message' => 'La méthode HTTP utilisée n\'est pas autorisée pour cette route.',
            ], 405);
        }

        if ($exception instanceof HttpException) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->getStatusCode());
        }

        // Gestion des erreurs générales
        return response()->json([
            'message' => 'Une erreur interne est survenue.',
            'error' => $exception->getMessage(),
        ], 500);
    }
}