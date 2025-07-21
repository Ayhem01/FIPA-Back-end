<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProjectBlockageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        
        return [
            'project_id' => $isUpdate ? 'sometimes|required|exists:projects,id' : 'required|exists:projects,id',
            'name' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            'type' => $isUpdate ? 'sometimes|required|string|max:100' : 'required|string|max:100',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,resolved,cancelled',
            'priority' => 'nullable|in:high,normal,low',
            'assigned_to' => 'nullable|exists:users,id',
            'follow_up_date' => 'nullable|date',
            'blocks_progress' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'project_id.required' => 'L\'ID du projet est obligatoire.',
            'project_id.exists' => 'Le projet sélectionné n\'existe pas.',
            'name.required' => 'Le nom du blocage est obligatoire.',
            'type.required' => 'Le type de blocage est obligatoire.',
            'assigned_to.exists' => 'L\'utilisateur assigné n\'existe pas.',
        ];
    }
}