<?php
namespace App\Services;

use App\Models\Blockage;
use App\Models\User;
use App\Notifications\BlockageEscalated;
use Illuminate\Support\Facades\Notification;

class BlockageService
{
    public function create(array $data): Blockage
    {
        return Blockage::create($data);
    }

    public function update(Blockage $blockage, array $data): Blockage
    {
        $blockage->update($data);
        return $blockage;
    }

    public function resolve(Blockage $blockage, int $resolvedBy, ?string $notes = null): Blockage
{
    $blockage->update([
        'status' => 'resolu',
        'resolved_by' => $resolvedBy,
        'resolved_at' => now(),
        'is_blocking' => false,
    ]);

    // Optionnel : enregistrer une note dans l’historique
    if ($notes) {
        $blockage->notes = $notes;
        $blockage->save();
    }

    return $blockage->fresh();
}

    public function delete(Blockage $blockage): bool
    {
        return $blockage->delete();
    }
    public function getBlockagesForStage(string $entityType, int $entityId, int $pipelineStageId)
    {
        // Ici on utilise les "types courts" car c'est ainsi qu'ils sont stockés en DB
        $blockableType = match($entityType) {
            'invite'     => 'invite',
            'prospect'   => 'prospect',
            'investor'   => 'investor',
            'projet'     => 'projet',
            default      => $entityType
        };

        return Blockage::with(['assignedUser:id,name', 'createdByUser:id,name', 'resolvedBy:id,name'])
            ->where('blockable_type', $blockableType)  // match direct avec la DB
            ->where('blockable_id', $entityId)
            ->where('pipeline_stageable_id', $pipelineStageId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function escalate(Blockage $blockage, $adminId): Blockage
    {
        if ($blockage->status !== 'actif' || $blockage->is_escalated) {
            return $blockage; // Déjà résolu ou déjà escaladé
        }

        $blockage->update([
            'priority'     => 'critical',
            'is_escalated' => true,
            'escalated_at' => now(),
            'assigned_to'  => $adminId // assigner à l'admin
        ]);

        // Envoyer notification à l'admin
        $admin = User::find($adminId);
        if ($admin) {
            $admin->notify(new BlockageEscalated($blockage));
        }

        // Créer un enregistrement d'activité pour l'escalade
        $this->recordBlockageActivity($blockage, 'escalated', auth()->id());

        return $blockage;
    }
}
