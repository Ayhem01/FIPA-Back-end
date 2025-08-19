<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitePipelineProgression extends Model
{
    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'invite_id',
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
     * L'invité associé à cette progression
     */
    public function invite(): BelongsTo
    {
        return $this->belongsTo(Invite::class);
    }

    /**
     * L'étape du pipeline associée à cette progression
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(InvitePipelineStage::class, 'stage_id');
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
     * Vérifier si cette étape permet la conversion en prospect
     */
    public function isConversionEligible(): bool
    {
        return $this->stage->conversion_eligible ?? false;
    }

    /**
     * Obtenir l'étape suivante dans le pipeline
     */
    public function nextStage()
    {
        return InvitePipelineStage::where('pipeline_type_id', $this->stage->pipeline_type_id)
            ->where('order', '>', $this->stage->order)
            ->orderBy('order')
            ->first();
    }

    /**
     * Obtenir l'étape précédente dans le pipeline
     */
    public function previousStage()
    {
        return InvitePipelineStage::where('pipeline_type_id', $this->stage->pipeline_type_id)
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
            'invite_id' => $this->invite_id,
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
     * Convertir l'invité en prospect si l'étape le permet
     */
    public function convertInviteToProspect($userId = null): ?Prospect
    {
        if (!$this->completed || !$this->isConversionEligible()) {
            return null;
        }

        return $this->invite->convertToProspect($userId);
    }

    /**
     * Synchroniser le statut de l'invitation avec cette étape
     */
    public function syncInviteStatus(): bool
    {
        $statusMap = [
            'Contact initial' => 'en_attente',
            'Initial Contact' => 'en_attente',
            'Invitation envoyée' => 'envoyee',
            'Invitation Sent' => 'envoyee',
            'Invitation confirmée' => 'confirmee',
            'Invitation Confirmed' => 'confirmee',
            'Participation' => 'participee',
            'Attendance' => 'participee',
            'Participated' => 'participee',
            'Refusé' => 'refusee',
            'Declined' => 'refusee',
            'Absent' => 'absente',
            'No-show' => 'absente'
        ];
        
        $stageName = $this->stage->name;
        
        if (isset($statusMap[$stageName])) {
            return $this->invite->update(['statut' => $statusMap[$stageName]]);
        }
        
        // Essai de correspondance par slug
        $slug = $this->stage->slug;
        foreach ($statusMap as $key => $value) {
            if (stripos($slug, str_replace(' ', '-', strtolower($key))) !== false) {
                return $this->invite->update(['statut' => $value]);
            }
        }
        
        return false;
    }
    
    /**
     * Calculer le score de l'invité basé sur sa progression
     */
    public function getInviteScoreAttribute(): int
    {
        $baseScore = 0;
        
        // Score basé sur l'étape actuelle
        $stageOrder = $this->stage->order;
        $maxOrder = InvitePipelineStage::where('pipeline_type_id', $this->stage->pipeline_type_id)
                                       ->max('order');
        
        if ($maxOrder > 0) {
            $baseScore += ($stageOrder / $maxOrder) * 50; // Maximum 50 points pour la position dans le pipeline
        }
        
        // Bonus si l'étape est complétée
        if ($this->completed) {
            $baseScore += 20; // +20 points si l'étape est complétée
        }
        
        // Bonus supplémentaire si c'est une étape finale
        if ($this->isFinalStage() && $this->completed) {
            $baseScore += 30; // +30 points si l'étape finale est complétée
        }
        
        return min(100, (int)round($baseScore));
    }
}