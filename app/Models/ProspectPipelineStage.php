<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProspectPipelineStage extends Model
{
    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'pipeline_type_id',
        'name',
        'slug',
        'description',
        'order',
        'is_final',
        'color',
        'status',
        'is_active',
        'created_by'
    ];

    /**
     * Les attributs à caster.
     *
     * @var array
     */
    protected $casts = [
        'is_final' => 'boolean',
        'is_active' => 'boolean',
        'order' => 'integer'
    ];

    /**
     * Le type de pipeline auquel cette étape appartient
     */
    public function pipelineType(): BelongsTo
    {
        return $this->belongsTo(ProspectPipelineType::class, 'pipeline_type_id');
    }

    /**
     * L'utilisateur qui a créé cette étape
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Les progressions associées à cette étape
     */
    public function progressions(): HasMany
    {
        return $this->hasMany(ProspectPipelineProgression::class, 'stage_id');
    }

    /**
     * Les prospects actuellement à cette étape
     */
    public function activeProspects()
    {
        return Prospect::whereHas('pipelineProgressions', function($query) {
            $query->where('stage_id', $this->id)
                  ->where('completed', false);
        });
    }

    /**
     * Les prospects qui ont complété cette étape
     */
    public function completedProspects()
    {
        return Prospect::whereHas('pipelineProgressions', function($query) {
            $query->where('stage_id', $this->id)
                  ->where('completed', true);
        });
    }

    /**
     * Obtenir la prochaine étape dans ce pipeline
     */
    public function nextStage()
    {
        return self::where('pipeline_type_id', $this->pipeline_type_id)
                  ->where('order', '>', $this->order)
                  ->where('is_active', true)
                  ->orderBy('order')
                  ->first();
    }

    /**
     * Obtenir l'étape précédente dans ce pipeline
     */
    public function previousStage()
    {
        return self::where('pipeline_type_id', $this->pipeline_type_id)
                  ->where('order', '<', $this->order)
                  ->where('is_active', true)
                  ->orderBy('order', 'desc')
                  ->first();
    }

    /**
     * Vérifier si cette étape est la première du pipeline
     */
    public function isFirstStage(): bool
    {
        return !self::where('pipeline_type_id', $this->pipeline_type_id)
                   ->where('order', '<', $this->order)
                   ->where('is_active', true)
                   ->exists();
    }

    /**
     * Vérifier si cette étape est la dernière du pipeline
     */
    public function isLastStage(): bool
    {
        return !self::where('pipeline_type_id', $this->pipeline_type_id)
                   ->where('order', '>', $this->order)
                   ->where('is_active', true)
                   ->exists();
    }

    /**
     * Déplacer cette étape vers le haut dans l'ordre du pipeline
     */
    public function moveUp(): bool
    {
        if ($this->isFirstStage()) {
            return false;
        }

        $previousStage = $this->previousStage();
        
        if (!$previousStage) {
            return false;
        }

        $currentOrder = $this->order;
        $previousOrder = $previousStage->order;

        $this->update(['order' => $previousOrder]);
        $previousStage->update(['order' => $currentOrder]);

        return true;
    }

    /**
     * Déplacer cette étape vers le bas dans l'ordre du pipeline
     */
    public function moveDown(): bool
    {
        if ($this->isLastStage()) {
            return false;
        }

        $nextStage = $this->nextStage();
        
        if (!$nextStage) {
            return false;
        }

        $currentOrder = $this->order;
        $nextOrder = $nextStage->order;

        $this->update(['order' => $nextOrder]);
        $nextStage->update(['order' => $currentOrder]);

        return true;
    }

    /**
     * Obtenir le nombre total de prospects actuellement à cette étape
     */
    public function getActiveProspectsCountAttribute(): int
    {
        return $this->progressions()
                   ->where('completed', false)
                   ->count();
    }

    /**
     * Obtenir la durée moyenne passée dans cette étape
     */
    public function getAverageDurationAttribute(): float
    {
        $completedProgressions = $this->progressions()
                                     ->where('completed', true)
                                     ->get();
        
        if ($completedProgressions->isEmpty()) {
            return 0;
        }

        $totalDays = $completedProgressions->sum(function ($progression) {
            return $progression->created_at->diffInDays($progression->completed_at);
        });

        return round($totalDays / $completedProgressions->count(), 1);
    }
}