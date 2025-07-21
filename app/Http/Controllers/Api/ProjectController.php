<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SuivieProjet\ProjectExceptionHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectRequest;
use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Project::query();
            
            // Filtre par secteur
            if ($request->has('sector_id')) {
                $query->where('sector_id', $request->sector_id);
            }
            
            // Filtre par état
            if ($request->has('status')) {
                $status = $request->status;
                if ($status === 'idea') {
                    $query->where('idea', true);
                } elseif ($status === 'in_progress') {
                    $query->where('in_progress', true);
                } elseif ($status === 'in_production') {
                    $query->where('in_production', true);
                }
            }
            
            // Filtre par gouvernorat
            if ($request->has('governorate_id')) {
                $query->where('governorate_id', $request->governorate_id);
            }
            
            // Filtre par type de pipeline
            if ($request->has('pipeline_type_id')) {
                $query->where('pipeline_type_id', $request->pipeline_type_id);
            }
            
            // Filtre par étape de pipeline
            if ($request->has('pipeline_stage_id')) {
                $query->where('pipeline_stage_id', $request->pipeline_stage_id);
            }
            
            // Recherche par nom d'entreprise ou titre
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('company_name', 'like', "%{$search}%");
                });
            }
            
            // Inclure les relations
            if ($request->has('with')) {
                $relations = explode(',', $request->with);
                $allowedRelations = ['sector', 'governorate', 'responsable', 'pipelineStage', 'pipelineType'];
                $validRelations = array_intersect($relations, $allowedRelations);
                $query->with($validRelations);
            }
            
            // Tri
            if ($request->has('sort_by')) {
                $sortField = $request->sort_by;
                $sortDirection = $request->sort_direction ?? 'asc';
                $query->orderBy($sortField, $sortDirection);
            } else {
                $query->latest();
            }
            
            // Pagination
            $perPage = $request->input('per_page', 15);
            $projects = $query->paginate($perPage);
            
            return response()->json($projects);
            
        } catch (\Exception $e) {
            return ProjectExceptionHandler::handle($e);
        }
    }

    public function store(ProjectRequest $request)
    {
        try {
            $data = $request->validated();
            $project = Project::create($data);
            
            return response()->json([
                'message' => 'Projet créé avec succès',
                'data' => $project
            ], 201);
        } catch (\Exception $e) {
            return ProjectExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $project = Project::with([
                'sector', 
                'governorate', 
                'responsable', 
                'pipelineType',
                'pipelineStage',
                'followUps' => function($query) {
                    $query->latest('follow_up_date')->limit(5);
                },
                'blockages' => function($query) {
                    $query->where('status', 'active');
                }
            ])->findOrFail($id);
            
            return response()->json($project);
        } catch (\Exception $e) {
            return ProjectExceptionHandler::handle($e);
        }
    }

    public function update(ProjectRequest $request, $id)
    {
        try {
            $project = Project::findOrFail($id);
            $project->update($request->validated());
            
            return response()->json([
                'message' => 'Projet mis à jour avec succès',
                'data' => $project
            ], 200);
        } catch (\Exception $e) {
            return ProjectExceptionHandler::handle($e);
        }
    }

    public function destroy($id)
    {
        try {
            $project = Project::findOrFail($id);
            $project->delete();
            
            return response()->json([
                'message' => "Le projet avec l'ID {$id} a été supprimé avec succès"
            ], 200);
        } catch (\Exception $e) {
            return ProjectExceptionHandler::handle($e);
        }
    }
    
    // Méthodes supplémentaires
    
    public function changeStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:idea,in_progress,in_production'
            ]);
            
            $project = Project::findOrFail($id);
            $status = $request->status;
            
            $project->idea = ($status === 'idea');
            $project->in_progress = ($status === 'in_progress');
            $project->in_production = ($status === 'in_production');
            $project->save();
            
            return response()->json([
                'message' => 'Statut du projet mis à jour avec succès',
                'data' => $project
            ], 200);
        } catch (\Exception $e) {
            return ProjectExceptionHandler::handle($e);
        }
    }
    
    public function updatePipelineStage(Request $request, $id)
    {
        try {
            $request->validate([
                'pipeline_stage_id' => 'required|exists:pipeline_stages,id'
            ]);
            
            $project = Project::findOrFail($id);
            $project->pipeline_stage_id = $request->pipeline_stage_id;
            $project->save();
            
            return response()->json([
                'message' => 'Étape du pipeline mise à jour avec succès',
                'data' => $project
            ], 200);
        } catch (\Exception $e) {
            return ProjectExceptionHandler::handle($e);
        }
    }
}