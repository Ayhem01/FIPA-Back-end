<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'start',
        'end',
        'all_day',
        'type',
        'status',
        'priority',
        'color',
        'user_id',
        'assignee_id',
        'reminder_24h_sent',
        'reminder_10min_sent',
        'entity_type',
        'entity_id',
        'pipeline_stage_id'
        
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'all_day' => 'boolean',
        'reminder_24h_sent' => 'boolean',
        'reminder_10min_sent' => 'boolean',
    ];

    /**
     * L'utilisateur qui a créé la tâche
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * L'utilisateur assigné à cette tâche
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function entity()
{
    return $this->morphTo();
}

public function pipelineStage()
{
    // ✅ CORRIGER - Ajouter la condition pour 'investisseur'
    if ($this->entity_type === 'invite') {
        return $this->belongsTo(InvitePipelineStage::class, 'pipeline_stage_id');
    } elseif ($this->entity_type === 'prospect') {
        return $this->belongsTo(ProspectPipelineStage::class, 'pipeline_stage_id');
    } elseif ($this->entity_type === 'investor' || $this->entity_type === 'investisseur') {
        return $this->belongsTo(InvestorPipelineStage::class, 'pipeline_stage_id');
    }
    
    return null;
}
   
   
}