<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaysRequest extends FormRequest
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
            'name_pays' => [
                'required',
                'string',
                'max:255',
                'min:2',
                Rule::unique('pays', 'name_pays')->ignore($this->route('id'))],
        ];
    }
    public function messages(): array
    {
        return [
            'name_pays.required' => 'Le nom de pays est requis.',
            'name_pays.string' => 'Le nom de pays doit être une chaîne de caractères.',
            'name_pays.max' => 'Le nom de pays ne doit pas dépasser 255 caractères.',
            'name_pays.min' => 'Le nom de pays doit comporter au moins 2 caractères.',
            'name_pays.unique' => 'Le nom de pays doit être unique.',
        ];
    }
}
