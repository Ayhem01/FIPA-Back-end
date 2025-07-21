<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GroupeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');

        return [
            'name' => [
                $isUpdate ? 'nullable' : 'required',
                'string',
                'max:255',
                 Rule::unique('groupes', 'name')->ignore($this->route('id')),
            ],
            'description' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le champ "nom" est requis.',
            'name.string' => 'Le champ "nom" doit être une chaîne de caractères.',
            'name.max' => 'Le champ "nom" ne doit pas dépasser 255 caractères.',
            'name.unique' => 'Ce nom de groupe existe déjà.',
            'description.string' => 'Le champ "description" doit être une chaîne de caractères.',
        ];
    }
}