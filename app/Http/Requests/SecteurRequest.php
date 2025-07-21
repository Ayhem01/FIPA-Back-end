<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class SecteurRequest extends FormRequest
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
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'min:2',
                Rule::unique('secteurs', 'name')->ignore($this->route('id'))
            ],
        ];
    
    }
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du secteur est requis.',
            'name.string' => 'Le nom du secteur doit être une chaîne de caractères.',
            'name.max' => 'Le nom du secteur ne doit pas dépasser 255 caractères.',
            'name.min' => 'Le nom du secteur doit comporter au moins 2 caractères.',
            'name.unique' => 'Le nom du secteur doit être unique.',
        ];
    }
}
