<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array
     */
    protected $table = 'projets';

    protected $fillable = [
        'title', 'description', 'company_name', 
        'idea', 'in_progress', 'in_production',
        'secteur_id', 'responsable_id',
        'market_target', 'nationality', 'foreign_percentage',
        'investment_amount', 'jobs_expected', 'industrial_zone',
        'pipeline_stage_id', 'is_blocked', 
        'pipeline_completed_at', 'pipeline_completed_by',
        'start_date', 'end_date',
        'contact_source', 'initial_contact_person', 'first_contact_date',
        'investisseur_id', 'status', 'created_by', 'notes',
        'converted_from_investisseur_at','region_id'
    ];

    /**
     * Les attributs à caster.
     *
     * @var array
     */
    protected $casts = [
        'idea' => 'boolean',
        'in_progress' => 'boolean',
        'in_production' => 'boolean',
        'is_blocked' => 'boolean',
        'foreign_percentage' => 'decimal:2',
        'investment_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'first_contact_date' => 'date',
        'converted_from_investisseur_at' => 'datetime',
        'pipeline_completed_at' => 'datetime', 

    ];

    /**
     * Les statuts possibles pour un projet
     */
    const STATUS_PLANNED = 'planned';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ABANDONED = 'abandoned';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_ON_HOLD = 'on_hold';

    /**
     * L'investisseur associé à ce projet
     */
    public function investisseur(): BelongsTo
    {
        return $this->belongsTo(Investisseur::class, 'investisseur_id');
    }

    /**
     * Le secteur d'activité du projet
     */
    public function secteur(): BelongsTo
    {
        return $this->belongsTo(Secteur::class);
    }
    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }
    
    /**
     * Le responsable du projet
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /**
     * Le créateur du projet
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function blockages()
{
    return $this->morphMany(Blockage::class, 'blockable');
}
    /**
     * Le type de pipeline utilisé par ce projet
     */
   
    
    /**
     * L'étape actuelle du pipeline (relation directe)
     */
    public function pipelineStage(): BelongsTo
    {
        return $this->belongsTo(ProjectPipelineStage::class, 'pipeline_stage_id');
    }

    /**
     * Les progressions dans le pipeline pour ce projet
     */
    public function pipelineProgressions(): HasMany
    {
        return $this->hasMany(ProjectPipelineProgression::class, 'projet_id');
    }

    public function currentStage()
    {
        return $this->pipelineStage;
    }

    public function initializePipeline($userId = null): bool
    {
        // Si le pipeline est déjà initialisé, ne rien faire
        if ($this->pipeline_stage_id) {
            return true;
        }
        
        // Obtenir la première étape active
        $firstStage = ProjectPipelineStage::where('is_active', true)
            ->orderBy('order')
            ->first();
        
        if (!$firstStage) {
            \Log::error("Aucune étape de pipeline active trouvée pour les projets");
            return false;
        }
        
        // Créer la progression
        $this->pipelineProgressions()->create([
            'stage_id' => $firstStage->id,
            'completed' => false,
            'assigned_to' => $userId ?? $this->responsable_id ?? auth()->id(),
            'notes' => 'Étape initiale créée automatiquement'
        ]);
        
        // Mettre à jour l'étape directe
        $this->update(['pipeline_stage_id' => $firstStage->id]);
        
        \Log::info("Pipeline initialisé avec succès pour projet #{$this->id}", [
            'stage_name' => $firstStage->name
        ]);
        
        return true;
    }

    /**
     * Obtenir l'étape actuelle du pipeline (via progressions)
     */
    public function getCurrentStageAttribute()
    {
        // D'abord essayer via le champ pipeline_stage_id direct
        if ($this->pipeline_stage_id) {
            return ProjectPipelineStage::find($this->pipeline_stage_id);
        }
        
        // Sinon chercher via les progressions
        return $this->pipelineProgressions()
                    ->where('completed', false)
                    ->orderBy('created_at', 'asc')
                    ->first()?->stage;
    }

    /**
     * Avancer à l'étape suivante du pipeline
     */
    public function advanceToNextStage($userId = null, $notes = null): bool
    {
        $currentStage = $this->pipelineStage;
        
        if (!$currentStage) {
            return $this->initializePipeline($userId);
        }
        
        try {
            return \DB::transaction(function () use ($currentStage, $userId, $notes) {
                // Marquer l'étape actuelle comme complétée
                $currentProgression = $this->pipelineProgressions()
                    ->where('stage_id', $currentStage->id)
                    ->where('completed', false)
                    ->first();
                
                if ($currentProgression) {
                    $currentProgression->update([
                        'completed' => true,
                        'completed_at' => now(),
                        'notes' => $notes ?: $currentProgression->notes
                    ]);
                }
                
                // Si c'est l'étape finale, marquer le projet comme complété
                if ($currentStage->is_final) {
                    $this->update([
                        'status' => self::STATUS_COMPLETED,
                        'pipeline_completed_at' => now(),
                        'pipeline_completed_by' => $userId ?? auth()->id()
                    ]);
                    
                    \Log::info("Étape finale atteinte pour le projet #{$this->id}");
                    return true;
                }
                
                // Trouver l'étape suivante
                $nextStage = ProjectPipelineStage::where('is_active', true)
                    ->where('order', '>', $currentStage->order)
                    ->orderBy('order')
                    ->first();
                
                if (!$nextStage) {
                    \Log::warning("Aucune étape suivante trouvée pour le projet #{$this->id}");
                    return false;
                }
                
                // Créer la progression pour l'étape suivante
                $this->pipelineProgressions()->firstOrCreate(
                    [
                        'stage_id' => $nextStage->id
                    ],
                    [
                        'completed' => false,
                        'assigned_to' => $userId ?? $this->responsable_id ?? auth()->id()
                    ]
                );
                
                // Mettre à jour l'étape actuelle
                $this->update(['pipeline_stage_id' => $nextStage->id]);
                
                \Log::info("Projet #{$this->id} avancé à l'étape suivante: {$nextStage->name}");
                return true;
            });
        } catch (\Exception $e) {
            \Log::error("Erreur lors de l'avancement du projet: " . $e->getMessage(), [
                'project_id' => $this->id,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Définir directement l'étape du projet
     */
    public function setStage($stageId, $userId = null, $notes = null): bool
    {
        try {
            $stage = ProjectPipelineStage::findOrFail($stageId);
            
            return \DB::transaction(function() use ($stage, $userId, $notes) {
                // Marquer toutes les progressions actuelles comme complétées
                $this->pipelineProgressions()
                    ->where('completed', false)
                    ->update(['completed' => true, 'completed_at' => now()]);
                
                // Créer la nouvelle progression
                $this->pipelineProgressions()->create([
                    'stage_id' => $stage->id,
                    'completed' => false,
                    'assigned_to' => $userId ?? $this->responsable_id ?? auth()->id(),
                    'notes' => $notes
                ]);
                
                // Mettre à jour l'étape directe
                $this->update(['pipeline_stage_id' => $stage->id]);
                
                // Si c'est l'étape finale, marquer comme complété
                if ($stage->is_final) {
                    $this->update([
                        'status' => self::STATUS_COMPLETED,
                        'pipeline_completed_at' => now(),
                        'pipeline_completed_by' => $userId ?? auth()->id()
                    ]);
                }
                
                return true;
            });
        } catch (\Exception $e) {
            \Log::error("Erreur lors de la définition de l'étape du projet: " . $e->getMessage(), [
                'project_id' => $this->id,
                'stage_id' => $stageId,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function progressionPercentage(): int
    {
        // Si le pipeline est marqué comme terminé, retourner 100%
        if ($this->pipeline_completed_at) {
            return 100;
        }
        
        // Si le projet est terminé, retourner 100%
        if ($this->status === self::STATUS_COMPLETED) {
            return 100;
        }
        
        // Si le projet est abandonné, retourner 0%
        if ($this->status === self::STATUS_ABANDONED) {
            return 0;
        }
        
        $totalStages = ProjectPipelineStage::where('is_active', true)->count();
        
        if ($totalStages === 0) {
            return 0;
        }
        
        $completedStages = $this->pipelineProgressions()
            ->where('completed', true)
            ->count();
        
        return (int) round(($completedStages / $totalStages) * 100);
    }

    public function isPipelineCompleted(): bool
    {
        return !is_null($this->pipeline_completed_at);
    }

    /**
     * Vérifier si le projet est en retard
     */
    public function isDelayed(): bool
    {
        if (!$this->end_date) {
            return false;
        }
        
        return $this->end_date->isPast() && $this->status !== self::STATUS_COMPLETED;
    }

    /**
     * Calculer le pourcentage d'avancement du projet
     */
    // public function getProgressPercentageAttribute(): int
    // {
    //     // Si le projet est terminé, retourner 100%
    //     if ($this->status === self::STATUS_COMPLETED) {
    //         return 100;
    //     }
        
    //     // Si le projet est abandonné, pas de progrès
    //     if ($this->status === self::STATUS_ABANDONED) {
    //         return 0;
    //     }
        
    //     // Calculer en fonction des progressions dans le pipeline
    //     $currentStage = $this->currentStage;
        
    //     if (!$currentStage) {
    //         return 0;
    //     }
        
    //     $pipelineType = ProjectPipelineType::find($this->pipeline_type_id);
        
    //     if (!$pipelineType) {
    //         return 0;
    //     }
        
    //     $totalStages = $pipelineType->stages()->count();
        
    //     if ($totalStages === 0) {
    //         return 0;
    //     }
        
    //     // Les étapes complétées + l'étape actuelle avec une pondération
    //     $completedStages = $this->pipelineProgressions()
    //                            ->where('completed', true)
    //                            ->count();
        
    //     return min(100, (int)round(($completedStages / $totalStages) * 100));
    // }

    public function canAdvanceToNextStage()
    {
        if (!$this->pipeline_stage_id) {
            return false;
        }

        $currentStage = $this->pipelineStage;
        if ($currentStage && $currentStage->is_final) {
            return false;
        }

        return true;
    }
    public function getNextStage()
    {
        if (!$this->pipeline_stage_id) {
            return null;
        }

        $currentStage = $this->pipelineStage;
        if (!$currentStage) {
            return null;
        }

        return ProjectPipelineStage::where('is_active', true)
            ->where('order', '>', $currentStage->order)
            ->orderBy('order', 'asc')
            ->first();
    }

    public function getPreviousStage()
{
    if (!$this->pipeline_stage_id) {
        return null;
    }

    $currentStage = $this->pipelineStage;
    if (!$currentStage) {
        return null;
    }

    return ProjectPipelineStage::where('is_active', true)
        ->where('order', '<', $currentStage->order)
        ->orderBy('order', 'desc')
        ->first();
}

    

    public function getPipelineStatusDetails()
    {
        if (!$this->pipeline_stage_id) {
            return null;
        }

        $currentStage = $this->pipelineStage;
        $allStages = ProjectPipelineStage::where('is_active', true)
            ->orderBy('order')
            ->get();
        
        $completedStages = $this->pipelineProgressions()
            ->where('completed', true)
            ->count();
        
        $totalStages = $allStages->count();
        $progressionPercentage = $totalStages > 0 ? round(($completedStages / $totalStages) * 100) : 0;
        
        return [
            'current_stage' => $currentStage,
            'completed_stages' => $completedStages,
            'total_stages' => $totalStages,
            'progression_percentage' => $progressionPercentage,
            'is_final_stage' => $currentStage ? $currentStage->is_final : false,
            'can_advance' => $this->canAdvanceToNextStage(),
            'next_stage' => $this->getNextStage(),
            'previous_stage' => $this->getPreviousStage(),
        ];
    }


    /**
     * Obtenir l'historique des étapes du projet
     */
    public function getStageHistory()
    {
        return $this->pipelineProgressions()
                   ->with(['stage', 'assignedTo'])
                   ->orderBy('created_at')
                   ->get()
                   ->map(function($progression) {
                       return [
                           'stage' => $progression->stage->name,
                           'created_at' => $progression->created_at,
                           'completed' => $progression->completed,
                           'completed_at' => $progression->completed_at,
                           'duration_days' => $progression->completed ? 
                               $progression->created_at->diffInDays($progression->completed_at) : 
                               $progression->created_at->diffInDays(now()),
                           'assigned_to' => $progression->assignedTo->name ?? 'N/A',
                           'notes' => $progression->notes
                       ];
                   });
    }
    
    /**
     * Obtenir le chemin complet de conversion
     */
    public function getConversionPathAttribute(): array
    {
        $path = [];
        
        // Ajouter l'investisseur
        if ($this->investisseur) {
            $path['investisseur'] = [
                'id' => $this->investisseur->id,
                'nom' => $this->investisseur->nom
            ];
            
            // Ajouter le prospect si disponible
            if ($this->investisseur->prospect) {
                $path['prospect'] = [
                    'id' => $this->investisseur->prospect->id,
                    'nom' => $this->investisseur->prospect->nom
                ];
                
                // Ajouter l'invité si disponible
                if ($this->investisseur->prospect->invite) {
                    $path['invite'] = [
                        'id' => $this->investisseur->prospect->invite->id,
                        'nom' => $this->investisseur->prospect->invite->getFullNameAttribute()
                    ];
                }
            }
        }
        
        // Ajouter le projet lui-même
        $path['project'] = [
            'id' => $this->id,
            'title' => $this->title
        ];
        
        return $path;
    }


    /**
     * Créer un projet à partir d'un investisseur
     */
    public static function createFromInvestor(
        Investisseur $investisseur, 
        array $projectData,
        $userId = null
    ): ?self {
        try {
            // Données de base du projet
            $projectData = array_merge([
                'investisseur_id' => $investisseur->id,
                'status' => self::STATUS_PLANNED,
                'responsable_id' => $userId ?? $investisseur->responsable_id,
                'created_by' => $userId ?? auth()->id(),
                'converted_from_investisseur_at' => now(),
                'company_name' => $investisseur->entreprise->nom ?? null,
                'secteur_id' => $investisseur->secteur_id,
                'investment_amount' => $investisseur->montant_investissement ?? null,
            ], $projectData);
            
            // Créer le projet
            $project = self::create($projectData);
            
            // Mettre à jour l'investisseur
            $investisseur->update([
                'statut' => 'investi',
                'project_id' => $project->id,
                'converted_to_project_at' => now()  // Assurez-vous que cette colonne existe
            ]);
            
            // Initialiser le pipeline du projet
            $project->initializePipeline($userId);
            
            // Créer l'enregistrement de conversion si la classe existe
            if (class_exists('App\Models\PipelineConversion')) {
                \App\Models\PipelineConversion::create([
                    'source_type' => 'investisseur',
                    'source_id' => $investisseur->id,
                    'target_type' => 'project',
                    'target_id' => $project->id,
                    'converted_by' => $userId ?? auth()->id(),
                    'conversion_notes' => "Projet créé à partir de l'investisseur #" . $investisseur->id
                ]);
            }
            
            return $project;
        } catch (\Exception $e) {
            \Log::error('Erreur lors de la création du projet depuis un investisseur: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Finalize the pipeline progression and mark the project as completed.
     */
    public function finalizePipelineProgression(): bool
    {
        $currentStage = $this->pipelineStage;

        // Check if the current stage is the final stage
        if (!$currentStage || !$currentStage->is_final) {
            \Log::warning("Cannot finalize pipeline: Project is not in the final stage.", [
                'project_id' => $this->id,
                'current_stage' => $currentStage?->name
            ]);
            return false;
        }

        try {
            return \DB::transaction(function () use ($currentStage) {
                // Mark all stages as completed
                $this->pipelineProgressions()
                    ->where('completed', false)
                    ->update(['completed' => true, 'completed_at' => now()]);

                // Mark the current stage as completed
                $currentProgression = $this->pipelineProgressions()
                    ->where('stage_id', $currentStage->id)
                    ->where('completed', false)
                    ->first();

                if ($currentProgression) {
                    $currentProgression->update([
                        'completed' => true,
                        'completed_at' => now()
                    ]);
                }

                // Mark the project as completed
                $this->update([
                    'status' => self::STATUS_COMPLETED,
                    'pipeline_completed_at' => now(),
                    'pipeline_completed_by' => auth()->id()
                ]);

                \Log::info("Pipeline finalized for project #{$this->id}", [
                    'project_id' => $this->id,
                    'final_stage' => $currentStage->name
                ]);

                return true;
            });
        } catch (\Exception $e) {
            \Log::error("Error finalizing pipeline for project #{$this->id}: " . $e->getMessage(), [
                'project_id' => $this->id,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}