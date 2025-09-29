<?php

namespace App\Services\Pipeline;

use App\Models\ProspectPipelineStage;

class ProspectPipelineService
{
    public function listStages()
    {
        return ProspectPipelineStage::orderBy('order')->get();
    }

    public function createStage(array $data)
    {
        return ProspectPipelineStage::create($data);
    }

    public function updateStage($id, array $data)
    {
        $stage = ProspectPipelineStage::findOrFail($id);
        $stage->update($data);
        return $stage;
    }

    public function deleteStage($id)
    {
        return ProspectPipelineStage::where('id', $id)->delete();
    }
}
