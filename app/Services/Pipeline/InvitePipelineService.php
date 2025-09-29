<?php

namespace App\Services\Pipeline;

use App\Models\InvitePipelineStage;

class InvitePipelineService
{
    public function listStages()
    {
        return InvitePipelineStage::orderBy('order')->get();
    }

    public function createStage(array $data)
    {
        return InvitePipelineStage::create($data);
    }

    public function updateStage($id, array $data)
    {
        $stage = InvitePipelineStage::findOrFail($id);
        $stage->update($data);
        return $stage;
    }

    public function deleteStage($id)
    {
        return InvitePipelineStage::where('id', $id)->delete();
    }
}
