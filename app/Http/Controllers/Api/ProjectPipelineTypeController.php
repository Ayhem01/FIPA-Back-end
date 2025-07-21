<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SuivieProjet\ProjectPipelineTypeExceptionHandler;
use App\Http\Controllers\Controller;
use App\Models\ProjectPipelineType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectPipelineTypeController extends Controller
{
    /**
     * Liste des types de pipeline
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = ProjectPipelineType::query();
            
            // Filtre par état actif
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active === 'true' || $request->is_active === '1');
            }
            
            // Inclure les relations
            if ($request->has('with_stages') && $request->with_stages === 'true') {
                $query->with(['stages' => function($q) {
                    $q->orderBy('order');
                }]);
            }
            
            // Tri
            if ($request->has('sort_by')) {
                $sortField = $request->sort_by;
                $sortDirection = $request->sort_direction ?? 'asc';
                $query->orderBy($sortField, $sortDirection);
            } else {
                $query->orderBy('order')->orderBy('name');
            }
            
            $pipelineTypes = $query->get();
            
            return response()->json($pipelineTypes);
            
        } catch (\Exception $e) {
            return ProjectPipelineTypeExceptionHandler::handle($e);
        }
    }

    /**
     * Affiche un type de pipeline spécifique
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $pipelineType = ProjectPipelineType::with(['stages' => function ($query) {
                $query->orderBy('order');
            }])->findOrFail($id);
            
            // Comptage des projets utilisant ce type de pipeline
            $projectCount = $pipelineType->projects()->count();
            $pipelineType->project_count = $projectCount;
            
            return response()->json($pipelineType);
            
        } catch (\Exception $e) {
            return ProjectPipelineTypeExceptionHandler::handle($e);
        }
    }

    /**
     * Enregistre un nouveau type de pipeline
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:project_pipeline_types,name',
                'description' => 'nullable|string',
                'order' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
            ]);
            
            // Générer automatiquement un slug si non fourni
            if (!isset($validated['slug'])) {
                $validated['slug'] = Str::slug($validated['name']);
            }
            
            // Définir l'ordre par défaut si non fourni
            if (!isset($validated['order'])) {
                $maxOrder = ProjectPipelineType::max('order');
                $validated['order'] = ($maxOrder !== null) ? $maxOrder + 1 : 0;
            }
            
            // Définir actif par défaut si non fourni
            if (!isset($validated['is_active'])) {
                $validated['is_active'] = true;
            }
            
            $pipelineType = ProjectPipelineType::create($validated);
            
            return response()->json([
                'message' => 'Type de pipeline créé avec succès',
                'data' => $pipelineType
            ], 201);
            
        } catch (\Exception $e) {
            return ProjectPipelineTypeExceptionHandler::handle($e);
        }
    }

    /**
     * Met à jour un type de pipeline
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $pipelineType = ProjectPipelineType::findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255|unique:project_pipeline_types,name,' . $id,
                'description' => 'nullable|string',
                'order' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
            ]);
            
            // Mettre à jour le slug si le nom est modifié
            if (isset($validated['name']) && $validated['name'] !== $pipelineType->name) {
                $validated['slug'] = Str::slug($validated['name']);
            }
            
            $pipelineType->update($validated);
            
            return response()->json([
                'message' => 'Type de pipeline mis à jour avec succès',
                'data' => $pipelineType
            ], 200);
            
        } catch (\Exception $e) {
            return ProjectPipelineTypeExceptionHandler::handle($e);
        }
    }

    /**
     * Supprime un type de pipeline
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $pipelineType = ProjectPipelineType::findOrFail($id);
            
            // Vérifier s'il y a des projets associés à ce type de pipeline
            if ($pipelineType->projects()->count() > 0) {
                return response()->json([
                    'message' => 'Ce type de pipeline ne peut pas être supprimé car il est utilisé par des projets.'
                ], 422);
            }
            
            // Vérifier s'il y a des étapes associées à ce type de pipeline
            if ($pipelineType->stages()->count() > 0) {
                return response()->json([
                    'message' => 'Ce type de pipeline ne peut pas être supprimé car il contient des étapes. Supprimez d\'abord les étapes.'
                ], 422);
            }
            
            $pipelineType->delete();
            
            return response()->json([
                'message' => 'Type de pipeline supprimé avec succès'
            ], 200);
            
        } catch (\Exception $e) {
            return ProjectPipelineTypeExceptionHandler::handle($e);
        }
    }
}