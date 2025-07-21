<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SuivieProjet\ProjectFollowUpExceptionHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectFollowUpRequest;
use App\Models\Project;
use App\Models\ProjectFollowUp;
use Illuminate\Http\Request;

class ProjectFollowUpController extends Controller
{
    public function index(Request $request, $projectId = null)
    {
        try {
            $query = ProjectFollowUp::query();
            
            if ($projectId) {
                $query->where('project_id', $projectId);
            }
            
            // Filtre par utilisateur
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            
            // Filtre par état
            if ($request->has('completed')) {
                $query->where('completed', $request->completed === 'true' || $request->completed === '1');
            }
            
            // Filtre par date
            if ($request->has('date_from')) {
                $query->where('follow_up_date', '>=', $request->date_from);
            }
            
            if ($request->has('date_to')) {
                $query->where('follow_up_date', '<=', $request->date_to);
            }
            
            // Inclure les relations
            if ($request->has('with')) {
                $relations = explode(',', $request->with);
                $allowedRelations = ['project', 'user'];
                $validRelations = array_intersect($relations, $allowedRelations);
                $query->with($validRelations);
            } else {
                $query->with(['project', 'user']);
            }
            
            // Tri
            if ($request->has('sort_by')) {
                $sortField = $request->sort_by;
                $sortDirection = $request->sort_direction ?? 'asc';
                $query->orderBy($sortField, $sortDirection);
            } else {
                $query->orderBy('follow_up_date', 'desc');
            }
            
            // Pagination
            $perPage = $request->input('per_page', 15);
            $followUps = $query->paginate($perPage);
            
            return response()->json($followUps);
        } catch (\Exception $e) {
            return ProjectFollowUpExceptionHandler::handle($e);
        }
    }

    public function store(ProjectFollowUpRequest $request, $projectId = null)
    {
        try {
            $data = $request->validated();
            
            if ($projectId) {
                $project = Project::findOrFail($projectId);
                $data['project_id'] = $project->id;
            }
            
            if (!isset($data['user_id'])) {
                $data['user_id'] = auth()->id();
            }
            
            $followUp = ProjectFollowUp::create($data);
            
            return response()->json([
                'message' => 'Suivi de projet créé avec succès',
                'data' => $followUp->load(['project', 'user'])
            ], 201);
        } catch (\Exception $e) {
            return ProjectFollowUpExceptionHandler::handle($e);
        }
    }

    public function show($id)
    {
        try {
            $followUp = ProjectFollowUp::with(['project', 'user'])->findOrFail($id);
            return response()->json($followUp);
        } catch (\Exception $e) {
            return ProjectFollowUpExceptionHandler::handle($e);
        }
    }

    public function update(ProjectFollowUpRequest $request, $id)
    {
        try {
            $followUp = ProjectFollowUp::findOrFail($id);
            $followUp->update($request->validated());
            
            return response()->json([
                'message' => 'Suivi de projet mis à jour avec succès',
                'data' => $followUp->load(['project', 'user'])
            ], 200);
        } catch (\Exception $e) {
            return ProjectFollowUpExceptionHandler::handle($e);
        }
    }

    public function destroy($id)
    {
        try {
            $followUp = ProjectFollowUp::findOrFail($id);
            $followUp->delete();
            
            return response()->json([
                'message' => "Le suivi de projet avec l'ID {$id} a été supprimé avec succès"
            ], 200);
        } catch (\Exception $e) {
            return ProjectFollowUpExceptionHandler::handle($e);
        }
    }
    
    // Marquer un suivi comme complété
    public function complete($id)
    {
        try {
            $followUp = ProjectFollowUp::findOrFail($id);
            $followUp->update(['completed' => true]);
            
            return response()->json([
                'message' => 'Suivi de projet marqué comme complété',
                'data' => $followUp->load(['project', 'user'])
            ], 200);
        } catch (\Exception $e) {
            return ProjectFollowUpExceptionHandler::handle($e);
        }
    }
    
    // Obtenir les prochains suivis à effectuer
    public function upcoming(Request $request)
    {
        try {
            $query = ProjectFollowUp::where('completed', false)
                ->where('next_follow_up_date', '>=', now())
                ->orderBy('next_follow_up_date');
                
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            
            $limit = $request->input('limit', 10);
            $followUps = $query->with(['project', 'user'])->limit($limit)->get();
            
            return response()->json($followUps);
        } catch (\Exception $e) {
            return ProjectFollowUpExceptionHandler::handle($e);
        }
    }
}