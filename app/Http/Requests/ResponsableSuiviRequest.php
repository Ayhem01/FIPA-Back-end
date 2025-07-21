<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResponsableSuiviRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        return [
            'nom' => $isUpdate ? 'nullable|string|max:255' : 'required|string|max:255',
            'prenom' => $isUpdate ? 'nullable|string|max:255' : 'required|string|max:255',
            'email' => $isUpdate ? 'nullable|email|max:255|unique:responsable_suivi,email,' . $this->route('id') : 'required|email|max:255|unique:responsable_suivi,email,' . $this->route('id'),
            'telephone' => 'nullable|string|max:20',
            'fonction' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le champ "Nom" est requis.',
            'prenom.required' => 'Le champ "Prénom" est requis.',
            'email.required' => 'Le champ "Email" est requis.',
            'email.email' => 'Le champ "Email" doit être une adresse email valide.',
            'email.unique' => 'Cet email est déjà utilisé.',
            'telephone.string' => 'Le champ "Téléphone" doit être une chaîne de caractères.',
            'telephone.max' => 'Le champ "Téléphone" ne doit pas dépasser 20 caractères.',
            'fonction.string' => 'Le champ "Fonction" doit être une chaîne de caractères.',
            'fonction.max' => 'Le champ "Fonction" ne doit pas dépasser 255 caractères.',
        ];
    }
}