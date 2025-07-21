<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NationaliteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                 Rule::unique('nationalites', 'name')->ignore($this->route('id')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le champ "nom" est requis.',
            'name.string' => 'Le champ "nom" doit être une chaîne de caractères.',
            'name.max' => 'Le champ "nom" ne doit pas dépasser 255 caractères.',
            'name.unique' => 'Cette nationalité existe déjà.',
        ];
    }
}