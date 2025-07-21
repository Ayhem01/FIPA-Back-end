<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiateurRequest extends FormRequest
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
            'name' => [
                $isUpdate ? 'nullable' : 'required',
                'string',
                'max:255',
                'min:3',
                Rule::unique('initiateurs', 'name')->ignore($this->route('id')),
            ],
            'email' => [
               $isUpdate ? 'nullable' : 'required',
                'email',
                'max:255',
                Rule::unique('initiateurs', 'email')->ignore($this->route('id')),
            ],
        ];

    } 
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est requis.',
            'name.string' => 'Le nom doit être une chaîne de caractères.',
            'name.max' => 'Le nom ne doit pas dépasser 255 caractères.',
            'name.min' => 'Le nom doit comporter au moins 3 caractères.',
            'name.unique' => 'Le nom doit être unique.',
            'email.required' => 'L\'email est requis.',
            'email.email' => 'L\'email doit être une adresse email valide.',
            'email.max' => 'L\'email ne doit pas dépasser 255 caractères.',
            'email.unique' => 'L\'email doit être unique.',
        ];
    
    }
}
