<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResponsableFipaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');

        return [
            'nom' => $isUpdate ? 'nullable|string|max:255' : 'required|string|max:255',
            'prenom' => $isUpdate ? 'nullable|string|max:255' : 'required|string|max:255',
            'email' => [
                $isUpdate ? 'nullable' : 'required', 
                'email',
                'max:255',
                $isUpdate
                    ? Rule::unique('responsable_fipa', 'email')->ignore($this->route('id'))
                    : 'unique:responsable_fipa,email',
            ],
            'telephone' => 'nullable|string|max:20',
            'fonction' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le champ "nom" est requis.',
            'prenom.required' => 'Le champ "prénom" est requis.',
            'email.required' => 'Le champ "email" est requis.',
            'email.email' => 'Le champ "email" doit être une adresse email valide.',
            'email.unique' => 'Cet email est déjà utilisé.',
            'telephone.string' => 'Le champ "téléphone" doit être une chaîne de caractères.',
            'fonction.string' => 'Le champ "fonction" doit être une chaîne de caractères.',
        ];
    }
}
