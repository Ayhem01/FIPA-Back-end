<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EtapeRequest extends FormRequest
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
            'description' => 'nullable|string',
            'ordre' => 'nullable|integer|min:0',
            'couleur' => 'nullable|string|max:20',
            'duree_estimee' => 'nullable|integer|min:1',
            'est_obligatoire' => 'boolean',
            'type' => 'required|string|in:invite,lead,investisseur,projet', 
        ];
        
        // Le champ action_id est obligatoire lors de la création mais optionnel en mise à jour
        if ($isUpdate) {
            $rules['action_id'] = 'sometimes|exists:actions,id';
        } else {
            $rules['action_id'] = 'required|exists:actions,id';
        }
        
        return $rules;
    }

    public function messages()
    {
        return [
            'nom.required' => 'Le nom de l\'étape est obligatoire',
            'action_id.required' => 'L\'action associée est obligatoire',
            'action_id.exists' => 'L\'action sélectionnée n\'existe pas',
            'ordre.integer' => 'L\'ordre doit être un nombre entier',
            'ordre.min' => 'L\'ordre ne peut pas être négatif',
            'duree_estimee.integer' => 'La durée estimée doit être un nombre entier',
            'duree_estimee.min' => 'La durée estimée doit être positive',
            'type.required' => 'Le type d\'étape est obligatoire',
            'type.in' => 'Le type d\'étape doit être l\'un des suivants : invite, lead, investisseur, projet',
        ];
    }
}