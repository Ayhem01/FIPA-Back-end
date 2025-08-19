<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPipelineProgression extends Model
{
    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'projet_id',
        'stage_id',
        'completed',
        'completed_at',
        'notes',
        'assigned_to'
    ];

    /**
     * Les attributs à caster.
     *
     * @var array
     */
    protected $casts = [
        'completed' => 'boolean',
        'completed_at' => 'datetime'
    ];

    /**
     * Le projet associé à cette progression
     */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'projet_id');
    }

    /**
     * L'étape du pipeline associée à cette progression
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectPipelineStage::class, 'stage_id');
    }

    /**
     * L'utilisateur responsable de cette étape
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Marquer cette étape comme complétée
     */
    public function complete($notes = null): bool
    {
        return $this->update([
            'completed' => true,
            'completed_at' => now(),
            'notes' => $notes ?? $this->notes
        ]);
    }

    /**
     * Réinitialiser cette étape comme non complétée
     */
    public function reset(): bool
    {
        return $this->update([
            'completed' => false,
            'completed_at' => null
        ]);
    }

    /**
     * Vérifier si c'est l'étape finale du pipeline
     */
    public function isFinalStage(): bool
    {
        return $this->stage->is_final;
    }

    /**
     * Obtenir l'étape suivante dans le pipeline
     */
    public function nextStage()
    {
        return ProjectPipelineStage::where('pipeline_type_id', $this->stage->pipeline_type_id)
            ->where('order', '>', $this->stage->order)
            ->orderBy('order')
            ->first();
    }

    /**
     * Obtenir l'étape précédente dans le pipeline
     */
    public function previousStage()
    {
        return ProjectPipelineStage::where('pipeline_type_id', $this->stage->pipeline_type_id)
            ->where('order', '<', $this->stage->order)
            ->orderBy('order', 'desc')
            ->first();
    }

    /**
     * Créer la prochaine progression dans le pipeline
     */
    public function createNextProgression($userId = null): ?self
    {
        $nextStage = $this->nextStage();
        
        if (!$nextStage) {
            return null;
        }

        return self::create([
            'projet_id' => $this->projet_id,
            'stage_id' => $nextStage->id,
            'completed' => false,
            'assigned_to' => $userId ?? $this->assigned_to
        ]);
    }

    /**
     * Obtenir la durée passée dans cette étape
     */
    public function getDurationAttribute()
    {
        $start = $this->created_at;
        $end = $this->completed_at ?? now();
        
        return $start->diffInDays($end);
    }
    
    /**
     * Ajouter une note à cette progression
     */
    public function addNote(string $note): bool
    {
        $currentNotes = $this->notes ?? '';
        $timestamp = now()->format('d/m/Y H:i');
        $newNote = "[{$timestamp}] {$note}";
        
        $updatedNotes = $currentNotes 
            ? $currentNotes . "\n\n" . $newNote 
            : $newNote;
            
        return $this->update([
            'notes' => $updatedNotes
        ]);
    }

    /**
     * Vérifier si cette progression est liée à une extension de projet
     */
    public function isExtensionProgress(): bool
    {
        return $this->stage->isExtensionStage();
    }
    
    /**
     * Vérifier si cette étape est en retard
     */
    public function isOverdue($daysThreshold = 30): bool
    {
        if ($this->completed) {
            return false;
        }
        
        $averageDuration = $this->stage->getAverageDurationAttribute();
        $thresholdDays = max($daysThreshold, $averageDuration * 1.5);
        
        return $this->created_at->addDays($thresholdDays)->isPast();
    }
}