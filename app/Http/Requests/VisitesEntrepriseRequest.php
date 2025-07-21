<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VisitesEntrepriseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->method() === 'PUT' || $this->method() === 'PATCH';

        return [
            'encadre_avec_programme' => 'nullable|boolean',
            'entreprise_importante' => 'nullable|boolean',
            'initiateur_id' => $isUpdate ? 'nullable|exists:initiateurs,id' : 'required|exists:initiateurs,id',
            'nombre_visites' => 'nullable|integer|min:0',
            'date_contact' => 'nullable|date',
            'raison_sociale' => $isUpdate ? 'nullable|string|max:255' : 'required|string|max:255',
            'responsable' => 'nullable|string|max:255',
            'fonction' => 'nullable|string|max:255',
            'nationalite_id' => $isUpdate ? 'nullable|exists:nationalites,id' : 'required|exists:nationalites,id',
            'secteur_id' => $isUpdate ? 'nullable|exists:secteurs,id' : 'required|exists:secteurs,id',
            'activite' => 'nullable|string',
            'adresse' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:20',
            'fax' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'site_web' => 'nullable|url|max:255',
            'date_visite' => $isUpdate ? 'nullable|date' : 'required|date',
                'pr' => 'nullable|in:Prévue,Réalisée',
            'responsable_suivi_id' => 'nullable|exists:responsable_suivi,id',
            'programme_pdf' => 'nullable|file|mimes:pdf|max:2048',
            'services_appreciation' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'raison_sociale.required' => 'Le champ "Raison sociale" est requis.',
            'raison_sociale.string' => 'Le champ "Raison sociale" doit être une chaîne de caractères.',
            'raison_sociale.max' => 'Le champ "Raison sociale" ne doit pas dépasser 255 caractères.',
            'initiateur_id.required' => 'Le champ "Initiateur" est requis.',
            'initiateur_id.exists' => 'L\'initiateur sélectionné est invalide.',
            'nationalite_id.required' => 'Le champ "Nationalité" est requis.',
            'nationalite_id.exists' => 'La nationalité sélectionnée est invalide.',
            'secteur_id.required' => 'Le champ "Secteur" est requis.',
            'secteur_id.exists' => 'Le secteur sélectionné est invalide.',
            'date_visite.required' => 'Le champ "Date de visite" est requis.',
            'date_visite.date' => 'Le champ "Date de visite" doit être une date valide.',
            'programme_pdf.mimes' => 'Le fichier "Programme PDF" doit être au format PDF.',
            'programme_pdf.max' => 'Le fichier "Programme PDF" ne doit pas dépasser 2 Mo.',
        ];
    }
}