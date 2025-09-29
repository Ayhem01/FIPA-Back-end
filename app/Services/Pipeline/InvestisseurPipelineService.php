<?php

namespace App\Services\Pipeline;

use App\Models\InvestorPipelineStage;

class InvestisseurPipelineService
{
    public function listStages()
    {
        return InvestorPipelineStage::orderBy('order')->get();
    }

    public function createStage(array $data)
    {
        return InvestorPipelineStage::create($data);
    }

    public function updateStage($id, array $data)
    {
        $stage = InvestorPipelineStage::findOrFail($id);
        $stage->update($data);
        return $stage;
    }

    public function deleteStage($id)
    {
        return InvestorPipelineStage::where('id', $id)->delete();
    }
}
