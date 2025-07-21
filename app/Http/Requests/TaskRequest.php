<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // À adapter selon votre logique d'autorisation
    }

    public function rules(): array
    {
        $isUpdate = $this->method() === 'PUT' || $this->method() === 'PATCH';
        
        return [
            'title' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            'description' => 'nullable|string',
            'start' => 'nullable|date',
            'end' => 'nullable|date|after_or_equal:start',
            'all_day' => 'nullable|boolean',
            'type' => [
                $isUpdate ? 'sometimes' : 'required',
                Rule::in(['call', 'meeting', 'email_journal', 'note', 'todo']),
            ],
            'status' => [
                'nullable',
                Rule::in(['not_started', 'in_progress', 'completed', 'deferred', 'waiting']),
            ],
            'priority' => [
                'nullable',
                Rule::in(['low', 'normal', 'high', 'urgent']),
            ],
            'color' => 'nullable|string|max:20',
            'assignee_id' => 'nullable|exists:users,id',
            
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Le titre de la tâche est obligatoire',
            'type.required' => 'Le type de tâche est obligatoire',
            'type.in' => 'Le type de tâche sélectionné n\'est pas valide',
            'end.after_or_equal' => 'La date de fin doit être égale ou postérieure à la date de début',
            'status.in' => 'Le statut sélectionné n\'est pas valide',
            'priority.in' => 'La priorité sélectionnée n\'est pas valide',
        ];
    }
}