<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Blockage extends Model
{
    protected $fillable = [
        'name',
        'description',
        'blockage_type',
        'status',
        'priority',
        'assigned_to',
        'created_by',
        'resolved_by',
        'resolved_at',
        'is_blocking',
        'blockable_type',
        'blockable_id',
        'pipeline_stageable_type',
        'pipeline_stageable_id',
        'escalated_at',
        'is_escalated'
    ];

    // Relation polymorphique avec entité (Invite, Prospect, etc.)
    public function blockable()
    {
        return $this->morphTo();
    }

    // Relation polymorphique avec un stage de pipeline
    public function pipelineStageable()
    {
        return $this->morphTo();
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOverdue($days = 3)
    {
        if ($this->status !== 'actif') {
            return false;
        }
        
        $deadlineDate = Carbon::now()->subDays($days);
        return $this->created_at->lt($deadlineDate);
    }
    public function autoEscalate($adminId)
{
    if ($this->status === 'actif' && !$this->is_escalated) {
        $this->update([
            'priority' => 'critical',
            'is_escalated' => true,
            'escalated_at' => now(),
            'assigned_to' => $adminId
        ]);
        
        // Notifier l'administrateur avec la notification spécifique
        $admin = User::find($adminId);
        if ($admin) {
            try {
                $admin->notify(new \App\Notifications\BlockageEscalated($this));
            } catch (\Exception $e) {
                \Log::error("Erreur lors de l'envoi de la notification d'auto-escalade: " . $e->getMessage());
            }
        }
    }
    
    return $this;
}
}
