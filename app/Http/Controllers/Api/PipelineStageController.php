<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SuivieProjet\PipelineStageExceptionHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\PipelineStageRequest;
use App\Models\PipelineStage;
use App\Models\ProjectPipelineType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PipelineStageController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = PipelineStage::query();
            
            // Filtre par type de pipeline
            if ($request->has('pipeline_type_id')) {
                $query->where('pipeline_type_id', $request->pipeline_type_id);
            }
            
            // Filtre par statut
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Filtre par état actif
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active === 'true' || $request->is_active === '1');
            }
            
            // Inclure les relations
            if ($request->has('with')) {
                $relations = explode(',', $request->with);
                $allowedRelations = ['pipelineType', 'projects'];
                $validRelations = array_intersect($relations, $allowedRelations);
                $query->with($validRelations);
            } else {
                $query->with(['pipelineType']);
            }
            
            // Tri
            if ($request->has('sort_by')) {
                $sortField = $request->sort_by;
                $sortDirection = $request->sort_direction ?? 'asc';
                $query->orderBy($sortField, $sortDirection);
            } else {
                $query->orderBy('pipeline_type_id')->orderBy('order');
            }
            
            // Pagination ou tout obtenir
            if ($request->has('all') && $request->all === 'true') {
                $stages = $query->get();
                return response()->json($stages);
            } else {
                $perPage = $request->input('per_page', 20);
                $stages = $query->paginate($perPage);
                return response()->json($stages);
            }
        } catch (\Exception $e) {
            return PipelineStageExceptionHandler::handle($e);
        }
    }

    public function store(PipelineStageRequest $request)
    {
        try {
            $data = $request->validated();
            
            // Générer un slug si non fourni
            if (!isset($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }
            
            // Déterminer l'ordre si non fourni
            if (!isset($data['order'])) {
                $maxOrder = PipelineStage::where('pipeline_type_id', $data['pipeline_type_id'])
                    ->max('order');
                $data['order'] = $maxOrder + 1;
            }
            
            $stage = PipelineStage::create($data);
            
            return response()->json([
                'message' => 'Étape de pipeline créée avec succès',
                'data' => $stage->load(['pipelineType'])
            ], 201);
        } catch (\Exception $e) {
            return PipelineStageExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $stage = PipelineStage::with(['pipelineType'])->findOrFail($id);
            return response()->json($stage);
        } catch (\Exception $e) {
            return PipelineStageExceptionHandler::handle($e);
        }
    }

    public function update(PipelineStageRequest $request, $id)
    {
        try {
            $stage = PipelineStage::findOrFail($id);
            $stage->update($request->validated());
            
            return response()->json([
                'message' => 'Étape de pipeline mise à jour avec succès',
                'data' => $stage->load(['pipelineType'])
            ], 200);
        } catch (\Exception $e) {
            return PipelineStageExceptionHandler::handle($e);
        }
    }

    public function destroy($id)
    {
        try {
            $stage = PipelineStage::findOrFail($id);
            
            // Vérifier s'il y a des projets associés à cette étape
            $projectsCount = $stage->projects()->count();
            if ($projectsCount > 0) {
                return response()->json([
                    'message' => "Cette étape est utilisée par {$projectsCount} projet(s) et ne peut pas être supprimée."
                ], 422);
            }
            
            $stage->delete();
            
            return response()->json([
                'message' => "L'étape de pipeline avec l'ID {$id} a été supprimée avec succès"
            ], 200);
        } catch (\Exception $e) {
            return PipelineStageExceptionHandler::handle($e);
        }
    }
    
    // Réorganiser les étapes
    public function reorder(Request $request)
    {
        try {
            $request->validate([
                'stages' => 'required|array',
                'stages.*.id' => 'required|exists:pipeline_stages,id',
                'stages.*.order' => 'required|integer|min:0'
            ]);
            
            foreach ($request->stages as $stageData) {
                PipelineStage::find($stageData['id'])->update([
                    'order' => $stageData['order']
                ]);
            }
            
            return response()->json([
                'message' => 'Ordre des étapes mis à jour avec succès'
            ], 200);
        } catch (\Exception $e) {
            return PipelineStageExceptionHandler::handle($e);
        }
    }
    
    // Obtenir toutes les étapes pour un type de pipeline
    public function getByPipelineType($pipelineTypeId)
    {
        try {
            $pipelineType = ProjectPipelineType::findOrFail($pipelineTypeId);
            
            $stages = PipelineStage::where('pipeline_type_id', $pipelineTypeId)
                ->orderBy('order')
                ->get();
                
            return response()->json([
                'pipeline_type' => $pipelineType,
                'stages' => $stages
            ]);
        } catch (\Exception $e) {
            return PipelineStageExceptionHandler::handle($e);
        }
    }
}