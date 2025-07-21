<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalonSectorielRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');

        return [
            'proposee' => 'nullable|boolean',
            'programmee' => 'nullable|boolean',
            'non_programmee' => 'nullable|boolean',
            'validee' => 'nullable|boolean',
            'realisee' => 'nullable|boolean',
            'reportee' => 'nullable|boolean',
            'annulee' => 'nullable|boolean',
            'motif' => 'nullable|string',
            'inclure' => 'nullable|in:comptabiliser,non comptabiliser',
            'initiateur_id' => $isUpdate ? 'nullable|exists:initiateurs,id' : 'required|exists:initiateurs,id',
            'intitule' => $isUpdate ? 'nullable|string|max:255' : 'required|string|max:255',
            'numero_edition' => 'nullable|string|max:255',
            'site_web' => 'nullable|string|max:255',
            'organisateur' => 'nullable|string|max:255',
            'convention_affaire' => 'nullable|string|max:255',
            'date_debut' => $isUpdate ? 'nullable|date' : 'required|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'pays_id' => $isUpdate ? 'nullable|exists:pays,id' : 'required|exists:pays,id',
            'region' => 'nullable|string|max:255',
            'theme' => 'nullable|string|max:255',
            'secteur_id' => $isUpdate ? 'nullable|exists:secteurs,id' : 'required|exists:secteurs,id',
            'groupe_id' =>  'nullable|exists:groupes,id',
            'categorie' => 'nullable|in:Incontournable,Prospection simple,Nouveau à prospecter',
            'presence_conjointe' => 'nullable|in:conjointe,non conjointe',
            'binome_id' => $isUpdate ? 'nullable|exists:binomes,id' : 'required|exists:binomes,id',
            'contacts_initiateur' => 'nullable|integer|min:0',
            'contacts_binome' => 'nullable|integer|min:0',
            'contacts_total' => 'nullable|integer|min:0',
            'contacts_interessants_initiateur' => 'nullable|integer|min:0',
            'contacts_interessants_binome' => 'nullable|integer|min:0',
            'objectif_contacts' => 'nullable|boolean',
            'objectif_veille_concurrentielle' => 'nullable|boolean',
            'objectif_veille_technologique' => 'nullable|boolean',
            'objectif_relation_relais' => 'nullable|boolean',
            'historique_edition' => 'nullable|string',
            'stand' => 'nullable|string',
            'media' => 'nullable|string',
            'besoin_binome' => 'nullable|string',
            'autre_organisme' => 'nullable|string',
            'outils_promotionnels' => 'nullable|string',
            'date_butoir' => 'nullable|date|after_or_equal:date_debut',
            'budget_prevu' => 'nullable|numeric|min:0',
            'budget_realise' => 'nullable|numeric|min:0',
            'resultat_veille_concurrentielle' => 'nullable|numeric|min:0',
            'resultat_veille_technologique' => 'nullable|numeric|min:0',
            'relation_institutions' => 'nullable|numeric|min:0',
            'evaluation_recommandations' => 'nullable|string',
            'contacts_realises' => 'nullable|integer|min:0',
        ];
    }
        public function messages(): array
        {
            return [
                'initiateur_id.required' => 'Le champ "initiateur_id" est obligatoire.',
                'initiateur_id.exists' => 'L\'initiateur sélectionné est invalide.',
                'intitule.required' => 'Le champ "intitulé" est obligatoire.',
                'intitule.max' => 'Le champ "intitulé" ne doit pas dépasser 255 caractères.',
                'date_debut.required' => 'Le champ "date de début" est obligatoire.',
                'date_debut.date' => 'Le champ "date de début" doit être une date valide.',
                'date_fin.date' => 'Le champ "date de fin" doit être une date valide.',
                'date_fin.after_or_equal' => 'La "date de fin" doit être postérieure ou égale à la "date de début".',
                'pays_id.required' => 'Le champ "pays_id" est obligatoire.',
                'pays_id.exists' => 'Le pays sélectionné est invalide.',
                'secteur_id.required' => 'Le champ "secteur_id" est obligatoire.',
                'secteur_id.exists' => 'Le secteur sélectionné est invalide.',
                'groupe_id.exists' => 'Le groupe sélectionné est invalide.',
                'binome_id.required' => 'Le champ "binome_id" est obligatoire.',
                'binome_id.exists' => 'Le binôme sélectionné est invalide.',
                'categorie.in' => 'La catégorie doit être l\'une des valeurs suivantes : Incontournable, Prospection simple, Nouveau à prospecter.',
                'presence_conjointe.in' => 'La présence conjointe doit être "conjointe" ou "non conjointe".',
                'budget_prevu.numeric' => 'Le champ "budget prévu" doit être un nombre.',
                'budget_prevu.min' => 'Le champ "budget prévu" doit être supérieur ou égal à 0.',
                'budget_realise.numeric' => 'Le champ "budget réalisé" doit être un nombre.',
                'budget_realise.min' => 'Le champ "budget réalisé" doit être supérieur ou égal à 0.',
                'contacts_realises.integer' => 'Le champ "contacts réalisés" doit être un entier.',
                'contacts_realises.min' => 'Le champ "contacts réalisés" doit être supérieur ou égal à 0.',
            ];
    }
}