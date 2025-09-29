<?php

namespace App\Services\Pipeline;

use App\Models\ProjectPipelineStage;

class ProjetPipelineService
{
    public function listStages()
    {
        return ProjectPipelineStage::orderBy('order')->get();
    }

    public function createStage(array $data)
    {
        return ProjectPipelineStage::create($data);
    }

    public function updateStage($id, array $data)
    {
        $stage = ProjectPipelineStage::findOrFail($id);
        $stage->update($data);
        return $stage;
    }

    public function deleteStage($id)
    {
        return ProjectPipelineStage::where('id', $id)->delete();
    }
}
