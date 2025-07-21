<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BinomeRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à effectuer cette requête.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles de validation pour la requête.
     */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');

        return [
            'name' => [
                $isUpdate ? 'nullable' : 'required',
                'string',
                'max:255',
                 Rule::unique('binomes', 'name')->ignore($this->route('id')),
            ],
            'email' => [
                $isUpdate ? 'nullable' : 'required',
                'email',
                'max:255',
                 Rule::unique('binomes', 'email')->ignore($this->route('id')),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],
            'poste' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Messages personnalisés pour les erreurs de validation.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du binôme est requis.',
            'name.string' => 'Le nom du binôme doit être une chaîne de caractères.',
            'name.max' => 'Le nom du binôme ne doit pas dépasser 255 caractères.',
            'name.unique' => 'Le nom du binôme doit être unique.',
            'email.required' => 'L\'email est requis.',
            'email.email' => 'L\'email doit être une adresse valide.',
            'email.unique' => 'L\'email doit être unique.',
            'phone.string' => 'Le numéro de téléphone doit être une chaîne de caractères.',
            'phone.max' => 'Le numéro de téléphone ne doit pas dépasser 20 caractères.',
            'poste.string' => 'Le poste doit être une chaîne de caractères.',
            'poste.max' => 'Le poste ne doit pas dépasser 255 caractères.',
        ];
    }
}