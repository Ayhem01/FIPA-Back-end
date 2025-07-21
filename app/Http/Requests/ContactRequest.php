<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        
        $rules = [
            'nom' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            'prenom' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            'fonction' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'telephone_fixe' => 'nullable|string|max:20',
            'telephone_mobile' => 'nullable|string|max:20',
            'adresse' => 'nullable|string',
            'ville' => 'nullable|string|max:100',
            'code_postal' => 'nullable|string|max:20',
            'pays' => 'nullable|string|max:100',
            'est_principal' => 'boolean',
            'linkedin' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'statut' => 'nullable|string|max:50',
            'date_naissance' => 'nullable|date',
            'proprietaire_id' => 'nullable|exists:users,id',
        ];
        
        // Le champ entreprise_id est obligatoire lors de la création mais optionnel en mise à jour
        if ($isUpdate) {
            $rules['entreprise_id'] = 'sometimes|required|exists:entreprises,id';
        } else {
            $rules['entreprise_id'] = 'required|exists:entreprises,id';
        }
        
        return $rules;
    }

    public function messages()
    {
        return [
            'nom.required' => 'Le nom est obligatoire',
            'prenom.required' => 'Le prénom est obligatoire',
            'email.email' => 'L\'adresse email doit être valide',
            'entreprise_id.required' => 'L\'entreprise associée est obligatoire',
            'entreprise_id.exists' => 'L\'entreprise sélectionnée n\'existe pas',
            'date_naissance.date' => 'La date de naissance doit être une date valide',
            'proprietaire_id.exists' => 'Le propriétaire sélectionné n\'existe pas'
        ];
    }
}