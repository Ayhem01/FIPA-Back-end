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
    protected $fillable = [
        'title', 'description', 'company_name', 
        'idea', 'in_progress', 'in_production',
        'secteur_id', 'governorate_id', 'responsable_id',
        'market_target', 'nationality', 'foreign_percentage',
        'investment_amount', 'jobs_expected', 'industrial_zone',
        'pipeline_type_id', 'pipeline_stage_id', 'is_blocked', 
        'start_date', 'end_date',
        'contact_source', 'initial_contact_person', 'first_contact_date',
        'investisseur_id', 'status', 'created_by', 'notes',
        'converted_from_investisseur_at'
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

    /**
     * Le type de pipeline utilisé par ce projet
     */
    public function pipelineType(): BelongsTo
    {
        return $this->belongsTo(ProjectPipelineType::class, 'pipeline_type_id');
    }
    
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
        $currentStage = $this->currentStage;
        
        if (!$currentStage) {
            // Obtenir la première étape du pipeline par défaut
            $pipelineType = $this->pipeline_type_id ? 
                ProjectPipelineType::find($this->pipeline_type_id) : 
                ProjectPipelineType::getDefault();
            
            if (!$pipelineType) {
                return false;
            }
            
            $firstStage = $pipelineType->stages()->orderBy('order')->first();
            
            if (!$firstStage) {
                return false;
            }
            
            // Créer la première progression
            ProjectPipelineProgression::create([
                'projet_id' => $this->id,
                'stage_id' => $firstStage->id,
                'completed' => false,
                'assigned_to' => $userId ?? $this->responsable_id
            ]);
            
            // Mettre à jour l'étape directe
            $this->update(['pipeline_stage_id' => $firstStage->id]);
            
            return true;
        }
        
        // Trouver la progression actuelle
        $currentProgression = $this->pipelineProgressions()
                                   ->where('stage_id', $currentStage->id)
                                   ->where('completed', false)
                                   ->first();
        
        if ($currentProgression) {
            // Marquer comme complété
            $currentProgression->update([
                'completed' => true,
                'completed_at' => now(),
                'notes' => $notes ?: $currentProgression->notes
            ]);
        }
        
        // Trouver l'étape suivante
        $nextStage = ProjectPipelineStage::where('pipeline_type_id', $currentStage->pipeline_type_id)
                                         ->where('order', '>', $currentStage->order)
                                         ->orderBy('order')
                                         ->first();
        
        if (!$nextStage) {
            // Si c'est la dernière étape, on considère le projet comme terminé
            if ($currentStage->is_final) {
                $this->update(['status' => self::STATUS_COMPLETED]);
            }
            return false;
        }
        
        // Créer la progression pour l'étape suivante
        ProjectPipelineProgression::create([
            'projet_id' => $this->id,
            'stage_id' => $nextStage->id,
            'completed' => false,
            'assigned_to' => $userId ?? $this->responsable_id
        ]);
        
        // Mettre à jour l'étape directe
        $this->update(['pipeline_stage_id' => $nextStage->id]);
        
        return true;
    }

    /**
     * Définir directement l'étape du projet
     */
    public function setStage($stageId, $userId = null, $notes = null): bool
    {
        $stage = ProjectPipelineStage::find($stageId);
        
        if (!$stage) {
            return false;
        }
        
        // Marquer toutes les progressions actuelles comme complétées
        $this->pipelineProgressions()
             ->where('completed', false)
             ->update(['completed' => true, 'completed_at' => now()]);
        
        // Créer la nouvelle progression
        ProjectPipelineProgression::create([
            'projet_id' => $this->id,
            'stage_id' => $stageId,
            'completed' => false,
            'assigned_to' => $userId ?? $this->responsable_id,
            'notes' => $notes
        ]);
        
        // Mettre à jour l'étape directe
        $this->update([
            'pipeline_stage_id' => $stageId,
            'pipeline_type_id' => $stage->pipeline_type_id
        ]);
        
        return true;
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
    public function getProgressPercentageAttribute(): int
    {
        // Si le projet est terminé, retourner 100%
        if ($this->status === self::STATUS_COMPLETED) {
            return 100;
        }
        
        // Si le projet est abandonné, pas de progrès
        if ($this->status === self::STATUS_ABANDONED) {
            return 0;
        }
        
        // Calculer en fonction des progressions dans le pipeline
        $currentStage = $this->currentStage;
        
        if (!$currentStage) {
            return 0;
        }
        
        $pipelineType = ProjectPipelineType::find($this->pipeline_type_id);
        
        if (!$pipelineType) {
            return 0;
        }
        
        $totalStages = $pipelineType->stages()->count();
        
        if ($totalStages === 0) {
            return 0;
        }
        
        // Les étapes complétées + l'étape actuelle avec une pondération
        $completedStages = $this->pipelineProgressions()
                               ->where('completed', true)
                               ->count();
        
        return min(100, (int)round(($completedStages / $totalStages) * 100));
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
                'project_id' => $project->id
            ]);
            
            // Initialiser le pipeline du projet
            $project->initializePipeline($userId);
            
            // Créer l'enregistrement de conversion
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
     * Initialiser le pipeline pour ce projet
     */
    public function initializePipeline($userId = null): bool
    {
        // Si le pipeline est déjà initialisé, ne rien faire
        if ($this->pipeline_type_id && $this->pipeline_stage_id) {
            return true;
        }
        
        // Sélectionner le type de pipeline par défaut
        $pipelineType = ProjectPipelineType::getDefault();
        
        if (!$pipelineType) {
            return false;
        }
        
        // Définir le type de pipeline
        $this->update(['pipeline_type_id' => $pipelineType->id]);
        
        // Obtenir la première étape
        $firstStage = $pipelineType->stages()->orderBy('order')->first();
        
        if (!$firstStage) {
            return false;
        }
        
        // Créer la progression
        ProjectPipelineProgression::create([
            'projet_id' => $this->id,
            'stage_id' => $firstStage->id,
            'completed' => false,
            'assigned_to' => $userId ?? $this->responsable_id
        ]);
        
        // Mettre à jour l'étape directe
        $this->update(['pipeline_stage_id' => $firstStage->id]);
        
        return true;
    }
}