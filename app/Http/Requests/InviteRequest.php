<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Vous pouvez ajouter une logique d'autorisation si nécessaire
    }

    public function rules()
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        $inviteId = $this->route('id');
        
        return [
            'entreprise_id' => $isUpdate ? 'sometimes|required|exists:entreprises,id' : 'required|exists:entreprises,id',
            'action_id' => $isUpdate ? 'sometimes|required|exists:actions,id' : 'required|exists:actions,id',
            'etape_id' => $isUpdate ? 'sometimes|required|exists:etapes,id' : 'required|exists:etapes,id',
            'nom' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            'prenom' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            'email' => [
                $isUpdate ? 'sometimes' : 'required',
                'email', 
                'max:255',
            ],
            'telephone' => 'nullable|string|max:20',
            'fonction' => 'nullable|string|max:255',
            'type_invite' => $isUpdate ? 'sometimes|required|in:interne,externe' : 'required|in:interne,externe',
            'statut' => [
                'nullable',
                Rule::in([
                    'en_attente', 'envoyee', 'confirmee', 'refusee','details_envoyes', 'participation_confirmee','participation_sans_suivi', 'absente','aucune_reponse'
                ])
            ],
            'suivi_requis' => 'boolean',
            'date_invitation' => 'nullable|date',
            'date_evenement' => 'nullable|date',
            'commentaires' => 'nullable|string',
            'proprietaire_id' => $isUpdate ? 'sometimes|required|exists:users,id' : 'required|exists:users,id',
        ];
    }

    public function messages()
    {
        return [
            'entreprise_id.required' => 'L\'entreprise est obligatoire',
            'entreprise_id.exists' => 'L\'entreprise sélectionnée n\'existe pas',
            'action_id.required' => 'L\'action est obligatoire',
            'action_id.exists' => 'L\'action sélectionnée n\'existe pas',
            'etape_id.required' => 'L\'étape est obligatoire',
            'etape_id.exists' => 'L\'étape sélectionnée n\'existe pas',
            'nom.required' => 'Le nom est obligatoire',
            'prenom.required' => 'Le prénom est obligatoire',
            'email.required' => 'L\'email est obligatoire',
            'email.email' => 'L\'email doit être une adresse valide',
            'type_invite.required' => 'Le type d\'invité est obligatoire',
            'type_invite.in' => 'Le type d\'invité doit être interne ou externe',
            'proprietaire_id.required' => 'Le propriétaire est obligatoire',
            'proprietaire_id.exists' => 'Le propriétaire sélectionné n\'existe pas',
        ];
    }
}