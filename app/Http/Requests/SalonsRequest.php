<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalonsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->method() === 'PUT' || $this->method() === 'PATCH';

        return [
            'proposee' => 'nullable||boolean',
            'programmee' => 'nullable||boolean',
            'non_programmee' => 'nullable||boolean',
            'validee' => 'nullable||boolean',
            'realisee' => 'nullable||boolean',
            'reportee' => 'nullable||boolean',
            'annulee' => 'nullable||boolean',
            'motif' => 'nullable|string',
            'inclure' => 'nullable|in:Yes,No,default:Yes',
            'initiateur_id' => $isUpdate ? 'nullable|exists:initiateurs,id' : 'required|exists:initiateurs,id',

            'intitule' => $isUpdate ? 'nullable|string|max:255' : 'required|string|max:255',
            'numero_edition' => 'nullable|string|max:255',
            'site_web' => 'nullable|string|max:255',
            'organisateur' => 'nullable|string|max:255',
            'date_debut' => $isUpdate ? 'nullable|date' : 'required|date',
            'date_fin' => 'nullable|date',
            'pays_id' => $isUpdate ? 'nullable|exists:pays,id' : 'required|exists:pays,id',
            'region' => 'nullable|string|max:255',
            'theme' => 'nullable|string|max:255',
            'categorie' => 'nullable|in:Incontournable,Prospection simple,Nouveau à prospecter',
            'presence_conjointe' => 'nullable|in:Conjointe,Non Conjointe',
            'binome_id' => $isUpdate ? 'nullable|exists:binomes,id' : 'required|exists:binomes,id',
            'contacts_initiateur' => 'nullable|integer',
            'contacts_binome' => 'nullable|integer',
            'contacts_total' => 'nullable|integer',
            'objectif_contacts' => 'boolean',
            'objectif_veille_concurrentielle' => 'boolean',
            'objectif_veille_technologique' => 'boolean',
            'objectif_relation_relais' => 'boolean',
            'historique_edition' => 'nullable|string',
            'besoin_stand' => 'nullable|string',
            'besoin_media' => 'nullable|string',
            'besoin_binome' => 'nullable|string',
            'besoin_autre_organisme' => 'nullable|string',
            'outils_promotionnels' => 'nullable|string',
            'date_butoir' => 'nullable|date',
            'budget_prevu' => 'nullable|numeric',
            'budget_realise' => 'nullable|numeric',
            'resultat_veille_concurrentielle' => 'nullable|string',
            'resultat_veille_technologique' => 'nullable|string',
            'resultat_relation_institutions' => 'nullable|string',
            'evaluation_recommandations' => 'nullable|string',
            'contacts_realises' => 'nullable|integer',
        ];
    }
}