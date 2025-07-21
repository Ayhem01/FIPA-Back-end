<?php

namespace App\Http\Controllers\Api;

use App\Models\Project;
use App\Models\ProjectContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class ProjectContactController extends Controller
{
    /**
     * Afficher la liste des contacts de projet.
     * Optionnellement filtrer par project_id
     */
    public function index(Request $request)
    {
        $query = ProjectContact::query();
        
        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        
        $contacts = $query->orderBy('created_at', 'desc')->paginate(10);
        
        return response()->json([
            'success' => true,
            'data' => $contacts
        ]);
    }

    /**
     * Enregistrer un nouveau contact de projet.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'required|exists:projects,id',
            'name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'is_primary' => 'boolean',
            'is_external' => 'boolean',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $contact = ProjectContact::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Contact créé avec succès',
            'data' => $contact
        ], 201);
    }

    /**
     * Afficher un contact de projet spécifique.
     */
    public function show(ProjectContact $contact)
    {
        return response()->json([
            'success' => true,
            'data' => $contact
        ]);
    }

    /**
     * Mettre à jour un contact de projet existant.
     */
    public function update(Request $request, ProjectContact $contact)
    {
        $validator = Validator::make($request->all(), [
            'project_id' => 'exists:projects,id',
            'name' => 'string|max:255',
            'title' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'is_primary' => 'boolean',
            'is_external' => 'boolean',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $contact->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Contact mis à jour avec succès',
            'data' => $contact
        ]);
    }

    /**
     * Définir un contact comme principal pour un projet.
     */
    public function setPrimary(ProjectContact $contact)
    {
        // Réinitialiser tous les contacts de ce projet
        ProjectContact::where('project_id', $contact->project_id)
            ->where('id', '!=', $contact->id)
            ->update(['is_primary' => false]);
        
        // Définir ce contact comme principal
        $contact->is_primary = true;
        $contact->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Contact défini comme principal',
            'data' => $contact
        ]);
    }

    /**
     * Supprimer un contact de projet.
     */
    public function destroy(ProjectContact $contact)
    {
        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact supprimé avec succès'
        ]);
    }
    
    /**
     * Liste des contacts pour un projet spécifique.
     */
    public function contactsByProject(Project $project)
    {
        $contacts = $project->contacts()->orderBy('is_primary', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $contacts
        ]);
    }
}