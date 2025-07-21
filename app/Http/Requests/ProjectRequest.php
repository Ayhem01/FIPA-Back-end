<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        
        return [
            'title' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            'description' => 'nullable|string',
            'company_name' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            
            // Statut du projet
            'idea' => 'nullable|boolean',
            'in_progress' => 'nullable|boolean',
            'in_production' => 'nullable|boolean',
            
            // Relations
            'secteur_id' => $isUpdate ? 'sometimes|required|exists:secteurs,id' : 'required|exists:secteurs,id',
            //'governorate_id' => 'nullable|exists:governorates,id',
            'responsable_id' => $isUpdate ? 'sometimes|required|exists:users,id' : 'required|exists:users,id',
            
            // Détails du projet
            'market_target' => 'nullable|in:local,export,both',
            'nationality' => 'nullable|string|max:100',
            'foreign_percentage' => 'nullable|numeric|min:0|max:100',
            'investment_amount' => 'nullable|numeric|min:0',
            'jobs_expected' => 'nullable|integer|min:0',
            'industrial_zone' => 'nullable|string|max:255',
            
            // Pipeline et suivi
            'pipeline_type_id' => 'nullable|exists:project_pipeline_types,id',
            'pipeline_stage_id' => 'nullable|exists:pipeline_stages,id',
            'is_blocked' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            
            // Origine du projet
            'contact_source' => 'nullable|in:action_promo,visite,reference,salon,direct,autre',
            'initial_contact_person' => 'nullable|string|max:255',
            'first_contact_date' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Le titre du projet est obligatoire.',
            'company_name.required' => 'Le nom de l\'entreprise est obligatoire.',
            'sector_id.required' => 'Le secteur d\'activité est obligatoire.',
            'sector_id.exists' => 'Le secteur sélectionné n\'existe pas.',
            'responsable_id.required' => 'Le responsable du projet est obligatoire.',
            'responsable_id.exists' => 'Le responsable sélectionné n\'existe pas.',
            'end_date.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }
}