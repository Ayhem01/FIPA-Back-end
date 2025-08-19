<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class InvitePipelineType extends Model
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
        return $this->hasMany(InvitePipelineStage::class, 'pipeline_type_id')
                    ->orderBy('order');
    }

    /**
     * Les progressions associées à ce type de pipeline via ses étapes
     */
    public function progressions(): HasManyThrough
    {
        return $this->hasManyThrough(
            InvitePipelineProgression::class, 
            InvitePipelineStage::class,
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
            \Log::error('Erreur lors de la définition du pipeline invité par défaut: ' . $e->getMessage());
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
                throw new \Exception("Échec de la création du nouveau type de pipeline invité");
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
                    'conversion_eligible' => $stage->conversion_eligible ?? false,
                    'created_by' => auth()->id() ?? $stage->created_by
                ]);
            }
            
            return $newPipelineType->fresh(['stages']);
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la duplication du pipeline invité: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtenir les invités qui utilisent ce type de pipeline
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function invites()
    {
        $stageIds = $this->stages()->pluck('id');
        
        if ($stageIds->isEmpty()) {
            return Invite::whereRaw('1 = 0'); // Retourne une requête vide
        }
        
        return Invite::whereHas('pipelineProgressions', function($query) use ($stageIds) {
            $query->whereIn('stage_id', $stageIds);
        })->distinct();
    }

    /**
     * Obtenir le nombre d'invités utilisant ce type de pipeline
     *
     * @return int
     */
    public function getInvitesCountAttribute(): int
    {
        return $this->invites()->count();
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
        
        $totalInvites = $this->invitesCount;
        
        if ($totalInvites === 0) {
            return [
                'total' => 0,
                'completed' => 0,
                'conversion_rate' => 0,
                'average_days' => 0,
                'has_stages' => true
            ];
        }
        
        // Nombre d'invités ayant complété tout le pipeline
        $finalStages = $this->stages()->where('is_final', true)->pluck('id');
        $completedCount = $finalStages->isEmpty() ? 0 : Invite::whereHas('pipelineProgressions', function($query) use ($finalStages) {
            $query->whereIn('stage_id', $finalStages)
                  ->where('completed', true);
        })->count();
        
        // Taux de conversion
        $conversionRate = $totalInvites > 0 ? round(($completedCount / $totalInvites) * 100, 1) : 0;
        
        // Durée moyenne dans le pipeline
        $progressions = $this->progressions()
                            ->with(['invite'])
                            ->get()
                            ->groupBy('invite_id');
        
        $totalDays = 0;
        $invitesWithData = 0;
        
        foreach ($progressions as $inviteProgressions) {
            if ($inviteProgressions->isNotEmpty()) {
                $firstDate = $inviteProgressions->min('created_at');
                $lastDate = $inviteProgressions->max(function($item) {
                    return $item->completed ? $item->completed_at : null;
                });
                
                if ($firstDate && $lastDate) {
                    $totalDays += $firstDate->diffInDays($lastDate);
                    $invitesWithData++;
                }
            }
        }
        
        $averageDays = $invitesWithData > 0 ? round($totalDays / $invitesWithData, 1) : 0;
        
        return [
            'total' => $totalInvites,
            'completed' => $completedCount,
            'conversion_rate' => $conversionRate,
            'average_days' => $averageDays,
            'has_stages' => true,
            'final_stages_count' => $finalStages->count()
        ];
    }

    /**
     * Obtenir les statistiques de conversion en prospects
     *
     * @return array
     */
    public function getProspectConversionStatsAttribute(): array
    {
        $totalInvites = $this->invitesCount;
        
        if ($totalInvites === 0) {
            return [
                'total' => 0,
                'converted' => 0,
                'conversion_rate' => 0
            ];
        }
        
        // Nombre d'invités convertis en prospects
        $convertedCount = Invite::whereHas('pipelineProgressions', function($query) {
            $query->whereHas('stage', function($q) {
                $q->where('pipeline_type_id', $this->id);
            });
        })->whereHas('prospect')->count();
        
        // Taux de conversion
        $conversionRate = $totalInvites > 0 ? round(($convertedCount / $totalInvites) * 100, 1) : 0;
        
        return [
            'total' => $totalInvites,
            'converted' => $convertedCount,
            'conversion_rate' => $conversionRate
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
                'conversion_eligible' => $stage->conversion_eligible ?? false
            ];
        }
        
        return $result;
    }

    /**
     * Obtenir les invités prêts à être convertis en prospects
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getConversionEligibleInvites()
    {
        $eligibleStageIds = $this->stages()
            ->where('conversion_eligible', true)
            ->pluck('id');
            
        if ($eligibleStageIds->isEmpty()) {
            return Invite::whereRaw('1 = 0'); // Retourne une requête vide
        }
        
        return Invite::whereHas('pipelineProgressions', function($query) use ($eligibleStageIds) {
            $query->whereIn('stage_id', $eligibleStageIds)
                  ->where('completed', true);
        })->whereDoesntHave('prospect')
          ->orderBy('updated_at', 'desc');
    }

    /**
     * Convertir automatiquement les invités éligibles en prospects
     *
     * @param int|null $userId
     * @return array
     */
    public function convertEligibleInvites(?int $userId = null): array
    {
        $eligibleInvites = $this->getConversionEligibleInvites()->get();
        $converted = [];
        $failed = [];
        
        foreach ($eligibleInvites as $invite) {
            $prospect = $invite->convertToProspect($userId);
            if ($prospect) {
                $converted[] = [
                    'invite_id' => $invite->id,
                    'prospect_id' => $prospect->id,
                    'name' => $invite->getFullNameAttribute()
                ];
            } else {
                $failed[] = [
                    'invite_id' => $invite->id,
                    'name' => $invite->getFullNameAttribute()
                ];
            }
        }
        
        return [
            'success_count' => count($converted),
            'failed_count' => count($failed),
            'converted' => $converted,
            'failed' => $failed
        ];
    }

    /**
     * Créer un type de pipeline par défaut avec des étapes communes
     *
     * @param int|null $createdBy
     * @return self|null
     */
    public static function createDefault(?int $createdBy = null): ?self
    {
        try {
            // Réinitialiser tout autre pipeline par défaut
            self::where('is_default', true)->update(['is_default' => false]);
            
            // Créer le nouveau type de pipeline
            $pipelineType = self::create([
                'name' => 'Pipeline invités standard',
                'slug' => 'standard-invite-pipeline',
                'description' => 'Pipeline de suivi standard pour les invités',
                'order' => 1,
                'is_active' => true,
                'is_default' => true,
                'created_by' => $createdBy
            ]);
            
            // Créer les étapes standard
            $stages = [
                [
                    'name' => 'Contact initial',
                    'slug' => 'initial-contact',
                    'description' => 'Première identification de l\'invité potentiel',
                    'order' => 10,
                    'color' => '#3498db',
                ],
                [
                    'name' => 'Invitation envoyée',
                    'slug' => 'invitation-sent',
                    'description' => 'L\'invitation a été envoyée à l\'invité',
                    'order' => 20,
                    'color' => '#9b59b6',
                ],
                [
                    'name' => 'Invitation confirmée',
                    'slug' => 'invitation-confirmed',
                    'description' => 'L\'invité a confirmé sa participation',
                    'order' => 30,
                    'color' => '#2ecc71',
                ],
                [
                    'name' => 'Participation',
                    'slug' => 'attendance',
                    'description' => 'L\'invité a participé à l\'événement',
                    'order' => 40,
                    'color' => '#f1c40f',
                ],
                [
                    'name' => 'Suivi post-événement',
                    'slug' => 'follow-up',
                    'description' => 'Suivi réalisé après l\'événement',
                    'order' => 50,
                    'color' => '#e67e22',
                ],
                [
                    'name' => 'Lead qualifié',
                    'slug' => 'qualified-lead',
                    'description' => 'L\'invité est qualifié comme prospect potentiel',
                    'order' => 60,
                    'color' => '#e74c3c',
                    'is_final' => true,
                    'conversion_eligible' => true
                ]
            ];
            
            foreach ($stages as $stage) {
                $pipelineType->stages()->create([
                    'name' => $stage['name'],
                    'slug' => $stage['slug'],
                    'description' => $stage['description'],
                    'order' => $stage['order'],
                    'is_final' => $stage['is_final'] ?? false,
                    'conversion_eligible' => $stage['conversion_eligible'] ?? false,
                    'color' => $stage['color'],
                    'status' => 'active',
                    'is_active' => true,
                    'created_by' => $createdBy
                ]);
            }
            
            return $pipelineType->fresh(['stages']);
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la création du pipeline invité par défaut: ' . $e->getMessage());
            return null;
        }
    }
}