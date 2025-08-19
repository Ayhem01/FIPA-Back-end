<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class InvestorPipelineType extends Model
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
        return $this->hasMany(InvestorPipelineStage::class, 'pipeline_type_id')
                    ->orderBy('order');
    }

    /**
     * Les progressions associées à ce type de pipeline via ses étapes
     */
    public function progressions(): HasManyThrough
    {
        return $this->hasManyThrough(
            InvestorPipelineProgression::class, 
            InvestorPipelineStage::class,
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
            \Log::error('Erreur lors de la définition du pipeline investisseur par défaut: ' . $e->getMessage());
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
                throw new \Exception("Échec de la création du nouveau type de pipeline investisseur");
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
            \Log::error('Erreur lors de la duplication du pipeline investisseur: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtenir les investisseurs qui utilisent ce type de pipeline
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function investisseurs()
    {
        $stageIds = $this->stages()->pluck('id');
        
        if ($stageIds->isEmpty()) {
            return Investisseur::whereRaw('1 = 0'); // Retourne une requête vide
        }
        
        return Investisseur::whereHas('pipelineProgressions', function($query) use ($stageIds) {
            $query->whereIn('stage_id', $stageIds);
        })->distinct();
    }

    /**
     * Obtenir le nombre d'investisseurs utilisant ce type de pipeline
     *
     * @return int
     */
    public function getInvestisseursCountAttribute(): int
    {
        return $this->investisseurs()->count();
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
        
        $totalInvestisseurs = $this->investisseursCount;
        
        if ($totalInvestisseurs === 0) {
            return [
                'total' => 0,
                'completed' => 0,
                'conversion_rate' => 0,
                'average_days' => 0,
                'has_stages' => true
            ];
        }
        
        // Nombre d'investisseurs ayant complété tout le pipeline
        $finalStages = $this->stages()->where('is_final', true)->pluck('id');
        $completedCount = $finalStages->isEmpty() ? 0 : Investisseur::whereHas('pipelineProgressions', function($query) use ($finalStages) {
            $query->whereIn('stage_id', $finalStages)
                  ->where('completed', true);
        })->count();
        
        // Taux de conversion
        $conversionRate = $totalInvestisseurs > 0 ? round(($completedCount / $totalInvestisseurs) * 100, 1) : 0;
        
        // Durée moyenne dans le pipeline
        $progressions = $this->progressions()
                            ->with(['investisseur'])
                            ->get()
                            ->groupBy('investisseur_id');
        
        $totalDays = 0;
        $investisseursWithData = 0;
        
        foreach ($progressions as $investisseurProgressions) {
            if ($investisseurProgressions->isNotEmpty()) {
                $firstDate = $investisseurProgressions->min('created_at');
                $lastDate = $investisseurProgressions->max(function($item) {
                    return $item->completed ? $item->completed_at : null;
                });
                
                if ($firstDate && $lastDate) {
                    $totalDays += $firstDate->diffInDays($lastDate);
                    $investisseursWithData++;
                }
            }
        }
        
        $averageDays = $investisseursWithData > 0 ? round($totalDays / $investisseursWithData, 1) : 0;
        
        return [
            'total' => $totalInvestisseurs,
            'completed' => $completedCount,
            'conversion_rate' => $conversionRate,
            'average_days' => $averageDays,
            'has_stages' => true,
            'final_stages_count' => $finalStages->count()
        ];
    }
}