<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MediaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        return [
            'proposee' => 'nullable|boolean',
            'programmee' => 'nullable|boolean',
            'realisee' => 'nullable|boolean',
            'reportee' => 'nullable|boolean',
            'annulee' => 'nullable|boolean',
            'action' => $isUpdate ? 'nullable|string|max:255' : 'required|string|max:255',
            'proposee par' => 'nullable|string|max:255',
            'type_action' => $isUpdate ? 'nullable|in:Annonce presse,Communique de presse,Article de presse,Interview,Reportage,Spécial pays,Conférence de presse,Affiche,Spot TV,Reportage TV,Film institutionnel,Spot radio,Bannière web' : 'required|in:Annonce presse,Communique de presse,Article de presse,Interview,Reportage,Spécial pays,Conférence de presse,Affiche,Spot TV,Reportage TV,Film institutionnel,Spot radio,Bannière web',
            'devise' => 'nullable|in:USD,EUR,TND,Yen',
            'imputation_financiere' => 'nullable|in:Régie au siège,Régie au RE',
            'type_media' => $isUpdate ? 'nullable|in:Magasine,Journal,Grouupe de publications,Newsletter externe,Bulletin d info,Chaine TV,Radios,Site internet,Espace d affichage' : 'required|in:Magasine,Journal,Grouupe de publications,Newsletter externe,Bulletin d info,Chaine TV,Radios,Site internet,Espace d affichage',
            'diffusion' => 'nullable|in:Locale,Régionale,Internationale',
            'evaluation' => 'nullable|in:Satisfaisante,Non satisfaisante,Tres satisfaisante',
            'reconduction' => 'nullable|in:Fortement recommandée,Déconseillée,Sans intéret',
            'duree' => $isUpdate ? 'nullable|string|max:50' : 'required|string|max:50',
            'zone_impact' => 'nullable|string',
            'cible' => 'nullable|string',
            'objectif' => 'nullable|string',
            'resultats_attendus' => 'nullable|string',
            'budget' => 'nullable|numeric|min:0',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'langue' => 'nullable|string|max:50',
            'tirage_audience' => 'nullable|string|max:50',
            'composition_lectorat' => 'nullable|string',
            'collaboration_fipa' => 'nullable|string',
            'volume_couverture' => 'nullable|string',
            'regie_publicitaire' => 'nullable|string',
            'media_contact' => 'nullable|string',
            'commentaires_specifiques' => 'nullable|string',

            // Validation des relations
            'nationalite_id' => $isUpdate ? 'nullable|exists:nationalites,id' : 'required|exists:nationalites,id',
            'responsable_bureau_media_id' => $isUpdate ? 'nullable|exists:responsables_bureau_media,id' : 'required|exists:responsables_bureau_media,id',
            'vav_siege_media_id' => 'nullable|exists:vav_sieges_media,id',
        ];
    }
    public function messages(): array
    {
        return [
            'nationalite_id.required' => 'Le champ nationalité est obligatoire.',
            'nationalite_id.exists' => 'La nationalité sélectionnée est invalide.',
            'responsable_bureau_media_id.required' => 'Le champ responsable bureau est obligatoire.',
            'responsable_bureau_media_id.exists' => 'Le responsable bureau sélectionné est invalide.',
            'vav_siege_media_id.exists' => 'Le VAV siège sélectionné est invalide.',
            'type_action.required' => 'Le champ type d\'action est obligatoire.',
            'type_action.in' => 'Le type d\'action sélectionné est invalide.',
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];
    }
}
