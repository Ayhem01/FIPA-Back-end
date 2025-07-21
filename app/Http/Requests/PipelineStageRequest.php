<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PipelineStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        
        return [
            'pipeline_type_id' => $isUpdate ? 'sometimes|required|exists:project_pipeline_types,id' : 'required|exists:project_pipeline_types,id',
            'name' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
           // 'slug' => $isUpdate ? 'sometimes|required|string|max:255|unique:pipeline_stages,slug,' . $this->pipeline_stage : 'required|string|max:255|unique:pipeline_stages,slug',
            'status' => 'nullable|in:open,success,lost',
            'color' => 'nullable|string|max:20',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'pipeline_type_id.required' => 'Le type de pipeline est obligatoire.',
            'pipeline_type_id.exists' => 'Le type de pipeline sélectionné n\'existe pas.',
            'name.required' => 'Le nom de l\'étape est obligatoire.',
            'slug.required' => 'Le slug de l\'étape est obligatoire.',
            'slug.unique' => 'Ce slug est déjà utilisé par une autre étape.',
        ];
    }
}