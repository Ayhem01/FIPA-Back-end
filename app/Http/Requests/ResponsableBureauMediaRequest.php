<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResponsableBureauMediaRequest extends FormRequest
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
            'name' => 'required|string|max:255|min:3|unique:responsables_bureau_media,name,' . ($isUpdate ? $this->route('responsable_bureau_media')->id : ''),
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
        ];
    }
}
