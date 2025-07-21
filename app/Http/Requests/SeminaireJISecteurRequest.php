<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SeminaireJISecteurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->method() === 'PUT' || $this->method() === 'PATCH';

        return [
            'secteur_id' => $isUpdate ? 'nullable|exists:secteurs,id' : 'required|exists:secteurs,id',
            'binome_id' => $isUpdate ? 'nullable|exists:binomes,id' : 'required|exists:binomes,id',
            'pays_id' => $isUpdate ? 'nullable|exists:pays,id' : 'required|exists:pays,id',
            'responsable_fipa_id' => $isUpdate ? 'nullable|exists:responsable_fipa,id' : 'required|exists:responsable_fipa,id',
            'groupe_id' => $isUpdate ? 'nullable|exists:groupes,id' : 'required|exists:groupes,id',
            'date_debut' => $isUpdate ? 'nullable|date' : 'required|date',
            'date_fin' => 'nullable|date|after:date_debut', // Validation : date_fin doit être après date_debut
            'outils_promotionnels' => 'nullable|string',
            'date_butoir' => 'nullable|date',
            'budget_prevu' => 'nullable|numeric|min:0',
            'budget_realise' => 'nullable|numeric|min:0',
            'nb_entreprises' => 'nullable|integer|min:0',
            'nb_multiplicateurs' => 'nullable|integer|min:0',
            'nb_institutionnels' => 'nullable|integer|min:0',
            'nb_articles_presse' => 'nullable|integer|min:0',
            'fichier_presence' => 'nullable|file|mimes:pdf|max:2048', // PDF file validation
            'evaluation_recommandations' => 'nullable|string',
            'contacts_realises' => 'nullable|integer|min:0',
            'inclure' => 'nullable|in:comptabilisée,non comptabilisée',
            'action_conjointe' => 'nullable|in:conjointe,non conjointe',
            'type_participation' => 'nullable|in:organisatrice,Co-organisateur,Participation active,simple présence',
            'type_organisation' => 'nullable|in:partenaires étrangers,partenaires tunisiens,les deux à la fois',
            'avec_diaspora' => 'nullable|in:organisée pour la diaspora,organisée avec la diaspora',
            'intitule' => $isUpdate ? 'nullable|string|max:255' : 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'secteur_id.required' => 'Le champ "Secteur" est requis.',
            'secteur_id.exists' => 'Le secteur sélectionné est invalide.',
            'binome_id.required' => 'Le champ "Binôme" est requis.',
            'binome_id.exists' => 'Le binôme sélectionné est invalide.',
            'pays_id.required' => 'Le champ "Pays" est requis.',
            'pays_id.exists' => 'Le pays sélectionné est invalide.',
            'responsable_fipa_id.required' => 'Le champ "Responsable FIPA" est requis.',
            'responsable_fipa_id.exists' => 'Le responsable FIPA sélectionné est invalide.',
            'groupe_id.required' => 'Le champ "Groupe" est requis.',
            'groupe_id.exists' => 'Le groupe sélectionné est invalide.',
            'date_debut.required' => 'Le champ "Date de début" est requis.',
            'date_debut.date' => 'Le champ "Date de début" doit être une date valide.',
            'date_fin.date' => 'Le champ "Date de fin" doit être une date valide.',
            'date_fin.after' => 'Le champ "Date de fin" doit être au moins un jour après la "Date de début".',
            'outils_promotionnels.string' => 'Le champ "Outils promotionnels" doit être une chaîne de caractères.',
            'date_butoir.date' => 'Le champ "Date butoir" doit être une date valide.',
            'budget_prevu.numeric' => 'Le champ "Budget prévu" doit être un nombre.',
            'budget_prevu.min' => 'Le champ "Budget prévu" doit être supérieur ou égal à 0.',
            'budget_realise.numeric' => 'Le champ "Budget réalisé" doit être un nombre.',
            'budget_realise.min' => 'Le champ "Budget réalisé" doit être supérieur ou égal à 0.',
            'nb_entreprises.integer' => 'Le champ "Nombre d\'entreprises" doit être un entier.',
            'nb_entreprises.min' => 'Le champ "Nombre d\'entreprises" doit être supérieur ou égal à 0.',
            'nb_multiplicateurs.integer' => 'Le champ "Nombre de multiplicateurs" doit être un entier.',
            'nb_multiplicateurs.min' => 'Le champ "Nombre de multiplicateurs" doit être supérieur ou égal à 0.',
            'nb_institutionnels.integer' => 'Le champ "Nombre d\'institutionnels" doit être un entier.',
            'nb_institutionnels.min' => 'Le champ "Nombre d\'institutionnels" doit être supérieur ou égal à 0.',
            'nb_articles_presse.integer' => 'Le champ "Nombre d\'articles de presse" doit être un entier.',
            'nb_articles_presse.min' => 'Le champ "Nombre d\'articles de presse" doit être supérieur ou égal à 0.',
            'fichier_presence.mimes' => 'Le fichier "Fichier de présence" doit être au format PDF.',
            'fichier_presence.max' => 'Le fichier "Fichier de présence" ne doit pas dépasser 2 Mo.',
            'evaluation_recommandations.string' => 'Le champ "Évaluation et recommandations" doit être une chaîne de caractères.',
            'contacts_realises.integer' => 'Le champ "Contacts réalisés" doit être un entier.',
            'contacts_realises.min' => 'Le champ "Contacts réalisés" doit être supérieur ou égal à 0.',
            'inclure.in' => 'Le champ "Inclure" doit être soit "comptabilisée" soit "non comptabilisée".',
            'action_conjointe.in' => 'Le champ "Action conjointe" doit être soit "conjointe" soit "non conjointe".',
            'type_participation.in' => 'Le champ "Type de participation" doit être une des valeurs suivantes : "organisatrice", "Co-organisateur", "Participation active", "simple présence".',
            'type_organisation.in' => 'Le champ "Type d\'organisation" doit être une des valeurs suivantes : "partenaires étrangers", "partenaires tunisiens", "les deux à la fois".',
            'avec_diaspora.in' => 'Le champ "Avec diaspora" doit être soit "organisée pour la diaspora" soit "organisée avec la diaspora".',
            'intitule.required' => 'Le champ "Intitulé" est requis.',
            'intitule.string' => 'Le champ "Intitulé" doit être une chaîne de caractères.',
        ];
    }
}