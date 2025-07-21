<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProjectFollowUpRequest extends FormRequest
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
            'user_id' => $isUpdate ? 'sometimes|required|exists:users,id' : 'required|exists:users,id',
            'follow_up_date' => $isUpdate ? 'sometimes|required|date' : 'required|date',
            'description' => $isUpdate ? 'sometimes|required|string' : 'required|string',
            'next_follow_up_date' => 'nullable|date|after_or_equal:follow_up_date',
            'completed' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'project_id.required' => 'L\'ID du projet est obligatoire.',
            'project_id.exists' => 'Le projet sélectionné n\'existe pas.',
            'user_id.required' => 'L\'ID de l\'utilisateur est obligatoire.',
            'user_id.exists' => 'L\'utilisateur sélectionné n\'existe pas.',
            'follow_up_date.required' => 'La date de suivi est obligatoire.',
            'description.required' => 'La description du suivi est obligatoire.',
            'next_follow_up_date.after_or_equal' => 'La date du prochain suivi doit être postérieure ou égale à la date de suivi actuelle.',
        ];
    }
}