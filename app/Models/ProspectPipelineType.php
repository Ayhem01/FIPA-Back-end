<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

use Illuminate\Support\Str; // Ajout de l'import manquant

class ProspectPipelineType extends Model
{
    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'order',
        'is_active',
        'is_default',
        'created_by'
    ];

    /**
     * Les attributs à caster.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'order' => 'integer'
    ];

    /**
     * L'utilisateur qui a créé ce type de pipeline
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Les étapes associées à ce type de pipeline
     */
    public function stages(): HasMany
    {
        return $this->hasMany(ProspectPipelineStage::class, 'pipeline_type_id')
                    ->orderBy('order');
    }

    /**
     * Les progressions associées à ce type de pipeline via ses étapes
     */
   public function progressions(): HasManyThrough
{
    return $this->hasManyThrough(
        ProspectPipelineProgression::class, 
        ProspectPipelineStage::class,
        'pipeline_type_id',
        'stage_id'
    );
}

    /**
     * Obtenir le type de pipeline par défaut
     *
     * @return self|null
     */
    public static function getDefault(): ?self
    {
        return self::where('is_default', true)
                  ->where('is_active', true)
                  ->first() ?? self::where('is_active', true)
                                  ->orderBy('order')
                                  ->first();
    }

    /**
     * Définir ce type de pipeline comme le type par défaut
     *
     * @return bool
     */
    public function setAsDefault(): bool
    {
        try {
            // Réinitialiser tous les autres types de pipeline par défaut
            self::where('is_default', true)
                ->where('id', '!=', $this->id)
                ->update(['is_default' => false]);
            
            // Définir celui-ci comme défaut
            return $this->update(['is_default' => true]);
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la définition du pipeline par défaut: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Dupliquer ce type de pipeline avec toutes ses étapes
     *
     * @param string|null $newName
     * @param bool $isActive
     * @return self|null
     */
    public function duplicate(string $newName = null, bool $isActive = true): ?self
    {
        try {
            $newName = $newName ?? $this->name . ' (copie)';
            $newSlug = Str::slug($newName); // Correction: Utilisez Str importé
            
            // Vérifier que le slug est unique
            $counter = 1;
            $baseSlug = $newSlug;
            while (self::where('slug', $newSlug)->exists()) {
                $newSlug = $baseSlug . '-' . $counter;
                $counter++;
            }
            
            $newPipelineType = self::create([
                'name' => $newName,
                'slug' => $newSlug,
                'description' => $this->description,
                'order' => self::max('order') + 1,
                'is_active' => $isActive,
                'is_default' => false,
                'created_by' => auth()->id() ?? $this->created_by
            ]);
            
            if (!$newPipelineType) {
                throw new \Exception("Échec de la création du nouveau type de pipeline");
            }
            
            // Dupliquer toutes les étapes
            foreach ($this->stages as $stage) {
                $newPipelineType->stages()->create([
                    'name' => $stage->name,
                    'slug' => $stage->slug . '-' . $newPipelineType->id,
                    'description' => $stage->description,
                    'order' => $stage->order,
                    'is_final' => $stage->is_final,
                    'color' => $stage->color,
                    'status' => $stage->status,
                    'is_active' => $stage->is_active,
                    'created_by' => auth()->id() ?? $stage->created_by
                ]);
            }
            
            return $newPipelineType->fresh(['stages']);
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la duplication du pipeline: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtenir les prospects qui utilisent ce type de pipeline
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function prospects()
    {
        $stageIds = $this->stages()->pluck('id');
        
        if ($stageIds->isEmpty()) {
            return Prospect::whereRaw('1 = 0'); // Retourne une requête vide
        }
        
        return Prospect::whereHas('pipelineProgressions', function($query) use ($stageIds) {
            $query->whereIn('stage_id', $stageIds);
        })->distinct();
    }

    /**
     * Obtenir le nombre de prospects utilisant ce type de pipeline
     *
     * @return int
     */
    public function getProspectsCountAttribute(): int
    {
        return $this->prospects()->count();
    }

    /**
     * Obtenir les statistiques de conversion pour ce pipeline
     *
     * @return array
     */
    public function getConversionStatsAttribute(): array
    {
        // Vérifier s'il y a des étapes dans ce pipeline
        if ($this->stages()->count() === 0) {
            return [
                'total' => 0,
                'completed' => 0,
                'conversion_rate' => 0,
                'average_days' => 0,
                'has_stages' => false
            ];
        }
        
        $totalProspects = $this->prospectsCount;
        
        if ($totalProspects === 0) {
            return [
                'total' => 0,
                'completed' => 0,
                'conversion_rate' => 0,
                'average_days' => 0,
                'has_stages' => true
            ];
        }
        
        // Nombre de prospects ayant complété tout le pipeline
        $finalStages = $this->stages()->where('is_final', true)->pluck('id');
        $completedCount = $finalStages->isEmpty() ? 0 : Prospect::whereHas('pipelineProgressions', function($query) use ($finalStages) {
            $query->whereIn('stage_id', $finalStages)
                  ->where('completed', true);
        })->count();
        
        // Taux de conversion
        $conversionRate = $totalProspects > 0 ? round(($completedCount / $totalProspects) * 100, 1) : 0;
        
        // Durée moyenne dans le pipeline
        $progressions = $this->progressions()
                            ->with(['prospect'])
                            ->get()
                            ->groupBy('prospect_id');
        
        $totalDays = 0;
        $prospectsWithData = 0;
        
        foreach ($progressions as $prospectProgressions) {
            if ($prospectProgressions->isNotEmpty()) {
                $firstDate = $prospectProgressions->min('created_at');
                $lastDate = $prospectProgressions->max(function($item) {
                    return $item->completed ? $item->completed_at : null;
                });
                
                if ($firstDate && $lastDate) {
                    $totalDays += $firstDate->diffInDays($lastDate);
                    $prospectsWithData++;
                }
            }
        }
        
        $averageDays = $prospectsWithData > 0 ? round($totalDays / $prospectsWithData, 1) : 0;
        
        return [
            'total' => $totalProspects,
            'completed' => $completedCount,
            'conversion_rate' => $conversionRate,
            'average_days' => $averageDays,
            'has_stages' => true,
            'final_stages_count' => $finalStages->count()
        ];
    }
}