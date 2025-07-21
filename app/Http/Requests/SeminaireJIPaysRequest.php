<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SeminaireJIPaysRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');

        return [
            'proposee' => 'nullable||boolean',
            'programmee' => 'nullable||boolean',
            'non_programmee' => 'nullable||boolean',
            'validee' => 'nullable||boolean',
            'realisee' => 'nullable||boolean',
            'reportee' => 'nullable||boolean',
            'annulee' => 'nullable||boolean',
            'motif' => 'nullable|string',
            'responsable_fipa_id' => $isUpdate ? 'nullable|exists:responsable_fipa,id' : 'required|exists:responsable_fipa,id',
            'inclure' => 'nullable|in:Yes,No',
            'intitule' => 'nullable|string|max:255',
            'theme' => 'nullable|string|max:255',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
            'pays_id' => $isUpdate ? 'nullable|exists:pays,id' : 'required|exists:pays,id',
            'region' => 'nullable|string|max:255',
            'action_conjointe' => 'nullable|string|max:255',
            'binome_id' => 'nullable|exists:binomes,id',
            'proposee_par' => 'nullable|string|max:255',
            'objectifs' => 'nullable|string',
            'lieu' => 'nullable|string|max:255',
            'type_participation' => 'nullable|in:Co-organisateur,Participation active,Simple présence',
            'details_participation_active' => 'nullable|string',
            'type_organisation' => 'nullable|in:partenaires étrangers,partenaires tunisiens,les deux à la fois',
            'partenaires_tunisiens' => 'nullable|string',
            'partenaires_etrangers' => 'nullable|string',
            'officiels' => 'nullable|string',
            'presence_dg' => 'boolean',
            'programme_deroulement' => 'boolean',
            'diaspora' => 'nullable|in:Pour la diaspora,Avec la diaspora',
            'diaspora_details' => 'nullable|string',
            'location_salle' => 'nullable|string',
            'media_communication' => 'nullable|string',
            'besoin_binome' => 'nullable|string',
            'autre_organisme' => 'nullable|string',
            'outils_promotionnels' => 'nullable|string',
            'date_butoir' => 'nullable|date',
            'budget_prevu' => 'nullable|numeric',
            'budget_realise' => 'nullable|numeric',
            'nb_entreprises' => 'nullable|integer',
            'nb_multiplicateurs' => 'nullable|integer',
            'nb_institutionnels' => 'nullable|integer',
            'nb_articles_presse' => 'nullable|integer',
            'fichier_pdf' => 'nullable|file|mimes:pdf|max:2048', // Validation pour le fichier PDF
            'evalutation_recommandation' => 'nullable|string',
        ];
    }

public function messages(): array
{
    return [
        'proposee.boolean' => 'Le champ "proposée" doit être un booléen.',
        'programmee.boolean' => 'Le champ "programmée" doit être un booléen..',
        'non_programmee.boolean' => 'Le champ "non programmée" doit être un booléen.',
        'validee.boolean' => 'Le champ "validée" doit être un booléen.',
        'realisee.boolean' => 'Le champ "réalisée" doit être un booléen.',
        'reportee.boolean' => 'Le champ "reportée" doit être un booléen.',
        'annulee.boolean' => 'Le champ "annulée" doit être un booléen.',
        'motif.string' => 'Le champ "motif" doit être une chaîne de caractères.',
        'initiateur_id.required' => 'Le champ "initiateur" est requis.',
        'initiateur_id.exists' => 'L\'initiateur sélectionné est invalide.',
        'inclure.in' => 'Le champ "inclure" doit être "Yes" ou "No".',
        'intitule.string' => 'Le champ "intitulé" doit être une chaîne de caractères.',
        'theme.string' => 'Le champ "thème" doit être une chaîne de caractères.',
        'date_debut.date' => 'Le champ "date de début" doit être une date valide.',
        'date_fin.date' => 'Le champ "date de fin" doit être une date valide.',
        'pays_id.required' => 'Le champ "pays" est requis.',
        'pays_id.exists' => 'Le pays sélectionné est invalide.',
        'region.string' => 'Le champ "région" doit être une chaîne de caractères.',
        'action_conjointe.string' => 'Le champ "action conjointe" doit être une chaîne de caractères.',
        'binome_id.exists' => 'Le binôme sélectionné est invalide.',
        'proposee_par.string' => 'Le champ "proposée par" doit être une chaîne de caractères.',
        'objectifs.string' => 'Le champ "objectifs" doit être une chaîne de caractères.',
        'lieu.string' => 'Le champ "lieu" doit être une chaîne de caractères.',
        'type_participation.in' => 'Le champ "type de participation" doit être l\'une des valeurs suivantes : "Co-organisateur", "Participation active", "Simple présence".',
        'details_participation_active.string' => 'Le champ "détails de participation active" doit être une chaîne de caractères.',
        'type_organisation.in' => 'Le champ "type d\'organisation" doit être l\'une des valeurs suivantes : "partenaires étrangers", "partenaires tunisiens", "les deux à la fois".',
        'partenaires_tunisiens.string' => 'Le champ "partenaires tunisiens" doit être une chaîne de caractères.',
        'partenaires_etrangers.string' => 'Le champ "partenaires étrangers" doit être une chaîne de caractères.',
        'officiels.string' => 'Le champ "officiels" doit être une chaîne de caractères.',
        'presence_dg.boolean' => 'Le champ "présence DG" doit être un booléen.',
        'programme_deroulement.boolean' => 'Le champ "programme déroulement" doit être un booléen.',
        'diaspora.in' => 'Le champ "diaspora" doit être l\'une des valeurs suivantes : "Pour la diaspora", "Avec la diaspora".',
        'diaspora_details.string' => 'Le champ "détails diaspora" doit être une chaîne de caractères.',
        'location_salle.string' => 'Le champ "location salle" doit être une chaîne de caractères.',
        'media_communication.string' => 'Le champ "média communication" doit être une chaîne de caractères.',
        'besoin_binome.string' => 'Le champ "besoin binôme" doit être une chaîne de caractères.',
        'autre_organisme.string' => 'Le champ "autre organisme" doit être une chaîne de caractères.',
        'outils_promotionnels.string' => 'Le champ "outils promotionnels" doit être une chaîne de caractères.',
        'date_butoir.date' => 'Le champ "date butoir" doit être une date valide.',
        'budget_prevu.numeric' => 'Le champ "budget prévu" doit être un nombre.',
        'budget_realise.numeric' => 'Le champ "budget réalisé" doit être un nombre.',
        'nb_entreprises.integer' => 'Le champ "nombre d\'entreprises" doit être un entier.',
        'nb_multiplicateurs.integer' => 'Le champ "nombre de multiplicateurs" doit être un entier.',
        'nb_institutionnels.integer' => 'Le champ "nombre d\'institutionnels" doit être un entier.',
        'nb_articles_presse.integer' => 'Le champ "nombre d\'articles de presse" doit être un entier.',
        'fichier_pdf.file' => 'Le champ "fichier PDF" doit être un fichier.',
        'fichier_pdf.mimes' => 'Le champ "fichier PDF" doit être un fichier de type PDF.',
        'fichier_pdf.max' => 'Le fichier PDF ne doit pas dépasser 2 Mo.',
        'evalutation_recommandation.string' => 'Le champ "évaluation et recommandations" doit être une chaîne de caractères.',
    ];
}

}