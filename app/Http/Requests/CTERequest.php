<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CTERequest extends FormRequest
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
            'prenom' => 'nullable|string|max:255',
            'nom' => $isUpdate ? 'nullable|string|max:255' : 'required|string|max:255',
            'adresse' => $isUpdate ? 'nullable|string|max:255' : 'required|string|max:255',
            'tel' => $isUpdate ? 'nullable|string|max:20' : 'required|string|max:20',
            'fax' => 'nullable|string|max:20',
            'email' => $isUpdate ? 'nullable|email|max:255' : 'required|email|max:255',
            'age' => 'nullable|string|max:3',
            'initiateur_id' => $isUpdate ? 'nullable|exists:initiateurs,id' : 'required|exists:initiateurs,id',
            'pays_id' => $isUpdate ? 'nullable|exists:pays,id' : 'required|exists:pays,id',
            'secteur_id' => $isUpdate ? 'nullable|exists:secteurs,id' : 'required|exists:secteurs,id',
            'date_contact' => 'nullable|date',
            'poste' => 'nullable|string|max:255',
            'diplome' => 'nullable|string|max:255',
            'ste' => 'nullable|string|max:255',
            'lieu' => 'nullable|string|max:255',
            'historique_date_debut' => 'nullable|date',
            'historique_ste' => 'nullable|boolean',
            'historique_poste' => 'nullable|string|max:255',
            'historique_date_fin' => 'nullable|date',
        ];

    }
    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom est requis.',
            'nom.string' => 'Le nom doit être une chaîne de caractères.',
            'nom.max' => 'Le nom ne doit pas dépasser 255 caractères.',
            'adresse.required' => 'L\'adresse est requise.',
            'tel.required' => 'Le numéro de téléphone est requis.',
            'email.required' => 'L\'email est requis.',
            'email.email' => 'L\'email doit être une adresse valide.',
            'initiateur_id.required' => 'L\'initiateur est requis.',
            'initiateur_id.exists' => 'L\'initiateur sélectionné est invalide.',
            'pays_id.required' => 'Le pays est requis.',
            'pays_id.exists' => 'Le pays sélectionné est invalide.',
            'secteur_id.required' => 'Le secteur est requis.',
            'secteur_id.exists' => 'Le secteur sélectionné est invalide.',
        ];
    }
}
