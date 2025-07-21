<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        
        return [
            'nom' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => $isUpdate ? 'sometimes|required|string|max:100' : 'required|string|max:100',
            'date_debut' => $isUpdate ? 'sometimes|required|date' : 'required|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'lieu' => 'nullable|string|max:255',
            'adresse' => 'nullable|string',
            'ville' => 'nullable|string|max:100',
            'code_postal' => 'nullable|string|max:20',
            'pays' => 'nullable|string|max:100',
            'virtuel' => 'boolean',
            'lien_virtuel' => 'nullable|string|url|max:255',
            'capacite_max' => 'nullable|integer|min:1',
            'entreprise_id' => 'nullable|exists:entreprises,id',
            'statut' => 'nullable|in:planifiee,en_preparation,confirmee,en_cours,terminee,annulee',
            'responsable_id' => 'nullable|exists:users,id',
            'notes_internes' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'nom.required' => 'Le nom de l\'action est obligatoire',
            'type.required' => 'Le type d\'action est obligatoire',
            'date_debut.required' => 'La date de début est obligatoire',
            'date_debut.date' => 'La date de début doit être une date valide',
            'date_fin.date' => 'La date de fin doit être une date valide',
            'date_fin.after_or_equal' => 'La date de fin doit être égale ou postérieure à la date de début',
            'lien_virtuel.url' => 'Le lien virtuel doit être une URL valide',
            'capacite_max.integer' => 'La capacité maximale doit être un nombre entier',
            'capacite_max.min' => 'La capacité maximale doit être au moins de 1',
            'entreprise_id.exists' => 'L\'entreprise sélectionnée n\'existe pas',
            'responsable_id.exists' => 'Le responsable sélectionné n\'existe pas',
        ];
    }
}