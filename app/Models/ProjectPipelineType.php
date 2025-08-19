<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class ProjectPipelineType extends Model
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
        return $this->hasMany(ProjectPipelineStage::class, 'pipeline_type_id')
                    ->orderBy('order');
    }

    /**
     * Les progressions associées à ce type de pipeline via ses étapes
     */
    public function progressions(): HasManyThrough
    {
        return $this->hasManyThrough(
            ProjectPipelineProgression::class, 
            ProjectPipelineStage::class,
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
            \Log::error('Erreur lors de la définition du pipeline projet par défaut: ' . $e->getMessage());
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
            $newSlug = Str::slug($newName);
            
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
                throw new \Exception("Échec de la création du nouveau type de pipeline projet");
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
            \Log::error('Erreur lors de la duplication du pipeline projet: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtenir les projets qui utilisent ce type de pipeline
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function projects()
    {
        $stageIds = $this->stages()->pluck('id');
        
        if ($stageIds->isEmpty()) {
            return Project::whereRaw('1 = 0'); // Retourne une requête vide
        }
        
        return Project::whereHas('pipelineProgressions', function($query) use ($stageIds) {
            $query->whereIn('stage_id', $stageIds);
        })->distinct();
    }

    /**
     * Obtenir le nombre de projets utilisant ce type de pipeline
     *
     * @return int
     */
    public function getProjectsCountAttribute(): int
    {
        return $this->projects()->count();
    }

    /**
     * Obtenir les statistiques de progression pour ce pipeline
     *
     * @return array
     */
    public function getProgressionStatsAttribute(): array
    {
        // Vérifier s'il y a des étapes dans ce pipeline
        if ($this->stages()->count() === 0) {
            return [
                'total' => 0,
                'completed' => 0,
                'success_rate' => 0,
                'average_days' => 0,
                'has_stages' => false
            ];
        }
        
        $totalProjects = $this->projectsCount;
        
        if ($totalProjects === 0) {
            return [
                'total' => 0,
                'completed' => 0,
                'success_rate' => 0,
                'average_days' => 0,
                'has_stages' => true
            ];
        }
        
        // Nombre de projets ayant complété tout le pipeline
        $finalStages = $this->stages()->where('is_final', true)->pluck('id');
        $completedCount = $finalStages->isEmpty() ? 0 : Project::whereHas('pipelineProgressions', function($query) use ($finalStages) {
            $query->whereIn('stage_id', $finalStages)
                  ->where('completed', true);
        })->count();
        
        // Taux de réussite
        $successRate = $totalProjects > 0 ? round(($completedCount / $totalProjects) * 100, 1) : 0;
        
        // Durée moyenne dans le pipeline
        $progressions = $this->progressions()
                            ->with(['project'])
                            ->get()
                            ->groupBy('project_id');
        
        $totalDays = 0;
        $projectsWithData = 0;
        
        foreach ($progressions as $projectProgressions) {
            if ($projectProgressions->isNotEmpty()) {
                $firstDate = $projectProgressions->min('created_at');
                $lastDate = $projectProgressions->max(function($item) {
                    return $item->completed ? $item->completed_at : null;
                });
                
                if ($firstDate && $lastDate) {
                    $totalDays += $firstDate->diffInDays($lastDate);
                    $projectsWithData++;
                }
            }
        }
        
        $averageDays = $projectsWithData > 0 ? round($totalDays / $projectsWithData, 1) : 0;
        
        return [
            'total' => $totalProjects,
            'completed' => $completedCount,
            'success_rate' => $successRate,
            'average_days' => $averageDays,
            'has_stages' => true,
            'final_stages_count' => $finalStages->count()
        ];
    }

    /**
     * Obtenir les statistiques par étape
     *
     * @return array
     */
    public function getStageStatsAttribute(): array
    {
        $result = [];
        
        foreach ($this->stages()->orderBy('order')->get() as $stage) {
            $activeCount = $stage->progressions()->where('completed', false)->count();
            $completedCount = $stage->progressions()->where('completed', true)->count();
            
            $result[] = [
                'id' => $stage->id,
                'name' => $stage->name,
                'color' => $stage->color,
                'order' => $stage->order,
                'active_count' => $activeCount,
                'completed_count' => $completedCount,
                'total_count' => $activeCount + $completedCount,
                'is_final' => $stage->is_final,
                'average_duration' => $stage->getAverageDurationAttribute()
            ];
        }
        
        return $result;
    }

    /**
     * Créer un pipeline vide sans étapes prédéfinies
     *
     * @param string $name
     * @param string $description
     * @param bool $isDefault
     * @param int|null $createdBy
     * @return self|null
     */
    public static function createEmpty(string $name, string $description = '', bool $isDefault = false, ?int $createdBy = null): ?self
    {
        try {
            $slug = Str::slug($name);
            
            // Vérifier que le slug est unique
            $counter = 1;
            $baseSlug = $slug;
            while (self::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            
            // Si ce pipeline doit être par défaut, réinitialiser les autres
            if ($isDefault) {
                self::where('is_default', true)->update(['is_default' => false]);
            }
            
            return self::create([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'order' => self::max('order') + 1,
                'is_active' => true,
                'is_default' => $isDefault,
                'created_by' => $createdBy ?? auth()->id()
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la création du pipeline projet vide: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Ajouter une étape au pipeline
     *
     * @param array $stageData
     * @return ProjectPipelineStage|null
     */
    public function addStage(array $stageData): ?ProjectPipelineStage
    {
        try {
            // Définir l'ordre si non fourni
            if (!isset($stageData['order'])) {
                $maxOrder = $this->stages()->max('order') ?? 0;
                $stageData['order'] = $maxOrder + 10;
            }
            
            // Générer le slug si non fourni
            if (!isset($stageData['slug'])) {
                $baseSlug = Str::slug($stageData['name']);
                $counter = 1;
                $slug = $baseSlug;
                
                while (ProjectPipelineStage::where('slug', $slug)
                        ->where('pipeline_type_id', $this->id)
                        ->exists()) {
                    $slug = $baseSlug . '-' . $counter;
                    $counter++;
                }
                
                $stageData['slug'] = $slug;
            }
            
            // Ajouter l'ID du pipeline
            $stageData['pipeline_type_id'] = $this->id;
            
            // Ajouter l'utilisateur créateur si non fourni
            if (!isset($stageData['created_by'])) {
                $stageData['created_by'] = auth()->id() ?? $this->created_by;
            }
            
            return $this->stages()->create($stageData);
        } catch (\Exception $e) {
            \Log::error('Erreur lors de l\'ajout d\'une étape au pipeline projet: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtenir les projets qui sont en retard dans ce pipeline
     *
     * @param int $thresholdDays Nombre de jours au-delà duquel un projet est considéré en retard
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDelayedProjects(int $thresholdDays = 30)
    {
        $delayedProjectIds = [];
        
        foreach ($this->stages as $stage) {
            $averageDuration = $stage->getAverageDurationAttribute();
            $threshold = max($thresholdDays, $averageDuration * 1.5);
            
            $progressions = $stage->progressions()
                ->where('completed', false)
                ->where('created_at', '<', now()->subDays($threshold))
                ->get();
                
            foreach ($progressions as $progression) {
                $delayedProjectIds[] = $progression->projet_id;
            }
        }
        
        return Project::whereIn('id', array_unique($delayedProjectIds))->get();
    }
}