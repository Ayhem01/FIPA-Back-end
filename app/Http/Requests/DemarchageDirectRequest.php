<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DemarchageDirectRequest extends FormRequest
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
            'realisee' => 'nullable|boolean',
            'reportee' => 'nullable|boolean',
            'annulee' => 'nullable|boolean',
            'presentation' => $isUpdate ? 'nullable|string' : 'required|string',
            'date_debut' => $isUpdate ? 'nullable|date' : 'required|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'nb_entreprises_ciblees' => 'nullable|integer|min:0',
            'source_ciblage' => 'nullable|string',
            'contacts_telephoniques' => 'nullable|integer|min:0',
            'lettre_argumentaire' => 'nullable|string',
            'nb_reponses_positives' => 'nullable|integer|min:0',
            'resultat_action' => 'nullable|string',
            'evaluation_action' => 'nullable|string',
            'initiateur_id' => $isUpdate ? 'nullable|exists:initiateurs,id' : 'required|exists:initiateurs,id',
            'secteur_id' => $isUpdate ? 'nullable|exists:secteurs,id' : 'required|exists:secteurs,id',
            'pays_id' => $isUpdate ? 'nullable|exists:pays,id' : 'required|exists:pays,id',
            'inclure' => 'nullable|in:comptabilisée,non comptabilisée',
            'groupe_secteur' => 'nullable|in:Aéronautique,Composants autos,Environnement,Offshoring,Santé,Industrie ferroviaire',
            'coinjointe' => 'nullable|in:conjointe,non conjointe',
            'cadre_siege' => 'nullable|in:binôme,vis-à-vis du siège',
        ];
    }

    public function messages(): array
    {
        return [
            'presentation.required' => 'Le champ "présentation" est obligatoire.',
            'presentation.string' => 'Le champ "présentation" doit être une chaîne de caractères.',
            'date_debut.required' => 'Le champ "date de début" est obligatoire.',
            'date_debut.date' => 'Le champ "date de début" doit être une date valide.',
            'date_fin.date' => 'Le champ "date de fin" doit être une date valide.',
            'date_fin.after_or_equal' => 'La "date de fin" doit être postérieure ou égale à la "date de début".',
            'nb_entreprises_ciblees.integer' => 'Le champ "nombre d\'entreprises ciblées" doit être un entier.',
            'nb_entreprises_ciblees.min' => 'Le champ "nombre d\'entreprises ciblées" doit être supérieur ou égal à 0.',
            'contacts_telephoniques.integer' => 'Le champ "contacts téléphoniques" doit être un entier.',
            'contacts_telephoniques.min' => 'Le champ "contacts téléphoniques" doit être supérieur ou égal à 0.',
            'nb_reponses_positives.integer' => 'Le champ "nombre de réponses positives" doit être un entier.',
            'nb_reponses_positives.min' => 'Le champ "nombre de réponses positives" doit être supérieur ou égal à 0.',
            'initiateur_id.required' => 'Le champ "initiateur" est obligatoire.',
            'initiateur_id.exists' => 'L\'initiateur sélectionné est invalide.',
            'secteur_id.required' => 'Le champ "secteur" est obligatoire.',
            'secteur_id.exists' => 'Le secteur sélectionné est invalide.',
            'pays_id.required' => 'Le champ "pays" est obligatoire.',
            'pays_id.exists' => 'Le pays sélectionné est invalide.',
            'inclure.in' => 'Le champ "inclure" doit être "comptabilisée" ou "non comptabilisée".',
            'groupe_secteur.in' => 'Le champ "groupe secteur" doit être une des valeurs suivantes : Aéronautique, Composants autos, Environnement, Offshoring, Santé, Industrie ferroviaire.',
            'coinjointe.in' => 'Le champ "coinjointe" doit être "conjointe" ou "non conjointe".',
            'cadre_siege.in' => 'Le champ "cadre siège" doit être "binôme" ou "vis-à-vis du siège".',
        ];
    }
}