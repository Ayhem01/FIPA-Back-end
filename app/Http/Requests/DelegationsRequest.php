<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DelegationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        return [
            'responsable_fipa_id' => $isUpdate ? 'nullable|exists:responsable_fipa,id' : 'required|exists:responsable_fipa,id',
            'initiateur_id' => $isUpdate ? 'nullable|exists:initiateurs,id' : 'required|exists:initiateurs,id',
            'nationalite_id' => $isUpdate ? 'nullable|exists:nationalites,id' : 'required|exists:nationalites,id',
            'groupe_id' => $isUpdate ? 'nullable|exists:groupes,id' : 'required|exists:groupes,id',
            'secteur_id' => $isUpdate ? 'nullable|exists:secteurs,id' : 'required|exists:secteurs,id',
            'date_visite' => $isUpdate ? 'nullable|date' : 'required|date',
            'delegation' => $isUpdate ? 'nullable|string|max:255' : 'required|string|max:255',
            'contact' => 'nullable|string|max:255',
            'fonction' => 'nullable|string|max:255',
            'adresse' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:20',
            'fax' => 'nullable|string|max:20',
            'email_site' => 'nullable|email|max:255',
            'activite' => 'nullable|string',
            'programme_visite' => 'nullable|string',
            'evaluation_suivi' => 'nullable|string',
            'liste_membres_pdf' => 'nullable|string',
            
        ];
    }

    public function messages(): array
    {
        return [
            'responsable_fipa_id.required' => 'Le champ "Responsable FIPA" est requis.',
            'responsable_fipa_id.exists' => 'Le Responsable FIPA sélectionné est invalide.',
            'initiateur_id.required' => 'Le champ "Initiateur" est requis.',
            'initiateur_id.exists' => 'L\'initiateur sélectionné est invalide.',
            'nationalite_id.required' => 'Le champ "Nationalité" est requis.',
            'nationalite_id.exists' => 'La nationalité sélectionnée est invalide.',
            'groupe_id.required' => 'Le champ "Groupe" est requis.',
            'groupe_id.exists' => 'Le groupe sélectionné est invalide.',
            'secteur_id.required' => 'Le champ "Secteur" est requis.',
            'secteur_id.exists' => 'Le secteur sélectionné est invalide.',
            'date_visite.required' => 'Le champ "Date de visite" est requis.',
            'date_visite.date' => 'Le champ "Date de visite" doit être une date valide.',
            'delegation.required' => 'Le champ "Délégation" est requis.',
            'delegation.string' => 'Le champ "Délégation" doit être une chaîne de caractères.',
            'delegation.max' => 'Le champ "Délégation" ne doit pas dépasser 255 caractères.',
            'contact.string' => 'Le champ "Contact" doit être une chaîne de caractères.',
            'contact.max' => 'Le champ "Contact" ne doit pas dépasser 255 caractères.',
            'fonction.string' => 'Le champ "Fonction" doit être une chaîne de caractères.',
            'fonction.max' => 'Le champ "Fonction" ne doit pas dépasser 255 caractères.',
            'adresse.string' => 'Le champ "Adresse" doit être une chaîne de caractères.',
            'adresse.max' => 'Le champ "Adresse" ne doit pas dépasser 255 caractères.',
            'telephone.string' => 'Le champ "Téléphone" doit être une chaîne de caractères.',
            'telephone.max' => 'Le champ "Téléphone" ne doit pas dépasser 20 caractères.',
            'fax.string' => 'Le champ "Fax" doit être une chaîne de caractères.',
            'fax.max' => 'Le champ "Fax" ne doit pas dépasser 20 caractères.',
            'email_site.email' => 'Le champ "Email" doit être une adresse email valide.',
            'email_site.max' => 'Le champ "Email" ne doit pas dépasser 255 caractères.',
            'activite.string' => 'Le champ "Activité" doit être une chaîne de caractères.',
            'programme_visite.string' => 'Le champ "Programme de visite" doit être une chaîne de caractères.',
            'evaluation_suivi.string' => 'Le champ "Évaluation et suivi" doit être une chaîne de caractères.',
        ];
    }
}