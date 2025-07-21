<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SuivieProjet\ProjectBlockageExceptionHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectBlockageRequest;
use App\Models\Project;
use App\Models\ProjectBlockage;
use Illuminate\Http\Request;

class ProjectBlockageController extends Controller
{
    public function index(Request $request, $projectId = null)
    {
        try {
            $query = ProjectBlockage::query();
            
            if ($projectId) {
                $query->where('project_id', $projectId);
            }
            
            // Filtre par statut
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            // Filtre par priorité
            if ($request->has('priority')) {
                $query->where('priority', $request->priority);
            }
            
            // Filtre par personne assignée
            if ($request->has('assigned_to')) {
                $query->where('assigned_to', $request->assigned_to);
            }
            
            // Inclure les relations
            if ($request->has('with')) {
                $relations = explode(',', $request->with);
                $allowedRelations = ['project', 'assignedUser'];
                $validRelations = array_intersect($relations, $allowedRelations);
                $query->with($validRelations);
            } else {
                $query->with(['project', 'assignedUser']);
            }
            
            // Tri
            if ($request->has('sort_by')) {
                $sortField = $request->sort_by;
                $sortDirection = $request->sort_direction ?? 'asc';
                $query->orderBy($sortField, $sortDirection);
            } else {
                $query->orderBy('created_at', 'desc');
            }
            
            // Pagination
            $perPage = $request->input('per_page', 15);
            $blockages = $query->paginate($perPage);
            
            return response()->json($blockages);
        } catch (\Exception $e) {
            return ProjectBlockageExceptionHandler::handle($e);
        }
    }

    public function store(ProjectBlockageRequest $request, $projectId = null)
    {
        try {
            $data = $request->validated();
            
            if ($projectId) {
                $project = Project::findOrFail($projectId);
                $data['project_id'] = $project->id;
            }
            
            $blockage = ProjectBlockage::create($data);
            
            // Si le blocage bloque la progression, marquer le projet comme bloqué
            if ($blockage->blocks_progress) {
                $blockage->project->update(['is_blocked' => true]);
            }
            
            return response()->json([
                'message' => 'Blocage créé avec succès',
                'data' => $blockage->load(['project', 'assignedUser'])
            ], 201);
        } catch (\Exception $e) {
            return ProjectBlockageExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $blockage = ProjectBlockage::with(['project', 'assignedUser'])->findOrFail($id);
            return response()->json($blockage);
        } catch (\Exception $e) {
            return ProjectBlockageExceptionHandler::handle($e);
        }
    }

    public function update(ProjectBlockageRequest $request, $id)
    {
        try {
            $blockage = ProjectBlockage::findOrFail($id);
            $oldBlocksProgress = $blockage->blocks_progress;
            
            $blockage->update($request->validated());
            
            // Gérer le blocage du projet
            if ($blockage->blocks_progress !== $oldBlocksProgress) {
                $project = $blockage->project;
                
                if ($blockage->blocks_progress) {
                    $project->update(['is_blocked' => true]);
                } else {
                    // Vérifier s'il reste d'autres blocages actifs qui bloquent la progression
                    $hasOtherBlockages = $project->blockages()
                        ->where('id', '!=', $blockage->id)
                        ->where('blocks_progress', true)
                        ->where('status', 'active')
                        ->exists();
                        
                    if (!$hasOtherBlockages) {
                        $project->update(['is_blocked' => false]);
                    }
                }
            }
            
            return response()->json([
                'message' => 'Blocage mis à jour avec succès',
                'data' => $blockage->load(['project', 'assignedUser'])
            ], 200);
        } catch (\Exception $e) {
            return ProjectBlockageExceptionHandler::handle($e);
        }
    }

    public function destroy($id)
    {
        try {
            $blockage = ProjectBlockage::findOrFail($id);
            $projectId = $blockage->project_id;
            $blocksProgress = $blockage->blocks_progress;
            
            $blockage->delete();
            
            // Si le blocage bloquait la progression, vérifier s'il reste d'autres blocages
            if ($blocksProgress) {
                $hasOtherBlockages = ProjectBlockage::where('project_id', $projectId)
                    ->where('blocks_progress', true)
                    ->where('status', 'active')
                    ->exists();
                    
                if (!$hasOtherBlockages) {
                    Project::find($projectId)->update(['is_blocked' => false]);
                }
            }
            
            return response()->json([
                'message' => "Le blocage avec l'ID {$id} a été supprimé avec succès"
            ], 200);
        } catch (\Exception $e) {
            return ProjectBlockageExceptionHandler::handle($e);
        }
    }
    
    // Méthode pour résoudre rapidement un blocage
    public function resolve($id)
    {
        try {
            $blockage = ProjectBlockage::findOrFail($id);
            $blockage->update([
                'status' => 'resolved'
            ]);
            
            // Si ce blocage bloquait la progression, vérifier s'il reste d'autres blocages
            if ($blockage->blocks_progress) {
                $hasOtherBlockages = ProjectBlockage::where('project_id', $blockage->project_id)
                    ->where('id', '!=', $blockage->id)
                    ->where('blocks_progress', true)
                    ->where('status', 'active')
                    ->exists();
                    
                if (!$hasOtherBlockages) {
                    $blockage->project->update(['is_blocked' => false]);
                }
            }
            
            return response()->json([
                'message' => 'Blocage résolu avec succès',
                'data' => $blockage->load(['project', 'assignedUser'])
            ], 200);
        } catch (\Exception $e) {
            return ProjectBlockageExceptionHandler::handle($e);
        }
    }
}