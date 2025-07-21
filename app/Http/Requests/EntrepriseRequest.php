<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EntrepriseRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        $entrepriseId = $this->route('id');
        
        $rules = [
            'nom' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'site_web' => 'nullable|url|max:255',
            'telephone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'adresse' => 'nullable|string',
            'ville' => 'nullable|string|max:100',
            'code_postal' => 'nullable|string|max:20',
            'pays' => 'nullable|string|max:100',
            'secteur_id' => 'nullable|exists:secteurs,id',
            'taille' => 'nullable|string|max:50',
            'capital' => 'nullable|numeric|min:0',
            'chiffre_affaires' => 'nullable|numeric|min:0',
            'date_creation' => 'nullable|date',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'statut' => 'nullable|string|in:prospect,actif,inactif,client,partenaire',
            'type' => 'nullable|string|in:entreprise,organisme_public,association,autre',
            'proprietaire_id' => 'nullable|exists:users,id',
            'pipeline_stage_id' => 'nullable|exists:pipeline_stages,id',
            'pipeline_type_id' => 'nullable|exists:project_pipeline_types,id',
        ];
        
        return $rules;
    }
    
    public function messages()
    {
        return [
            'nom.required' => 'Le nom de l\'entreprise est obligatoire',
            'logo.image' => 'Le logo doit être une image',
            'logo.mimes' => 'Le logo doit être au format jpeg, png, jpg ou gif',
            'logo.max' => 'Le logo ne doit pas dépasser 2Mo',
            'site_web.url' => 'L\'URL du site web doit être valide',
            'email.email' => 'L\'adresse email doit être valide',
            'capital.numeric' => 'Le capital doit être un nombre',
            'capital.min' => 'Le capital ne peut pas être négatif',
            'chiffre_affaires.numeric' => 'Le chiffre d\'affaires doit être un nombre',
            'chiffre_affaires.min' => 'Le chiffre d\'affaires ne peut pas être négatif',
            'date_creation.date' => 'La date de création doit être une date valide',
            'secteur_id.exists' => 'Le secteur sélectionné n\'existe pas',
            'proprietaire_id.exists' => 'L\'utilisateur sélectionné n\'existe pas',
            'pipeline_stage_id.exists' => 'L\'étape de pipeline sélectionnée n\'existe pas',
            'pipeline_type_id.exists' => 'Le type de pipeline sélectionné n\'existe pas',
        ];
    }
}