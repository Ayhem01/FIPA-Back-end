<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VisitesEntreprise;
use App\Http\Requests\VisitesEntrepriseRequest;
use App\Exceptions\VisitesEntrepriseExceptionHandler;
class VisitesEntrepriseController extends Controller
{

    public function index()
    {
        try {
            $visites = VisitesEntreprise::with(['initiateur', 'nationalite', 'secteur', 'responsableSuivi'])->get();

            if ($visites->isEmpty()) {
                return response()->json([
                    'message' => 'Aucune visite entreprise trouvée.'
                ], 404);
            }

            return response()->json($visites, 200);
        } catch (\Exception $e) {
            return VisitesEntrepriseExceptionHandler::handle($e);
        }
    }

    public function store(VisitesEntrepriseRequest $request)
    {
        try {
            $data = $request->validated();
            $visite = VisitesEntreprise::create($data);

            return response()->json([
                'message' => 'Visite entreprise créée avec succès',
                'data' => $visite
            ], 201);
        } catch (\Exception $e) {
            return VisitesEntrepriseExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $visite = VisitesEntreprise::with(['initiateur', 'nationalite', 'secteur', 'responsableSuivi'])->findOrFail($id);
            return response()->json($visite, 200);
        } catch (\Exception $e) {
            return VisitesEntrepriseExceptionHandler::handle($e);
        }
    }

    public function update(VisitesEntrepriseRequest $request, $id)
    {
        try {
            $visite = VisitesEntreprise::findOrFail($id);
            $visite->update($request->validated());

            return response()->json([
                'message' => 'Visite entreprise mise à jour avec succès',
                'data' => $visite
            ], 200);
        } catch (\Exception $e) {
            return VisitesEntrepriseExceptionHandler::handle($e);
        }
    }

    public function destroy($id)
    {
        try {
            $visite = VisitesEntreprise::findOrFail($id);
            $visite->delete();

            return response()->json([
                'message' => "Visite entreprise avec l'ID {$id} a été supprimée avec succès"
            ], 200);
        } catch (\Exception $e) {
            return VisitesEntrepriseExceptionHandler::handle($e);
        }
    }
}