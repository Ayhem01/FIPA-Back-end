<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use App\Models\InvestorPipelineStage;      // ✅ AJOUTER
use App\Models\InvestorPipelineProgression; // ✅ AJOUTER

class Investisseur extends Model
{
    use SoftDeletes;

    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array
     */
    protected $table = 'investisseurs';

    protected $fillable = [
        'entreprise_id',
        'nom',
        'prospect_id',
        'email',
        'telephone',
        'adresse',
        'pays_id',
        'secteur_id',
        'montant_investissement',
        'devise',
        'interets_specifiques',
        'criteres_investissement',
        'statut',
        'date_engagement',
        'date_signature',
        'responsable_id',
        'created_by',
        'notes_internes',
        'date_dernier_contact',
        'prochain_contact_prevu',
        'converted_to_project_at',
        'project_id',
        'pipeline_stage_id'
        

    ];

    /**
     * Les attributs à caster.
     *
     * @var array
     */
    protected $casts = [
        'montant_investissement' => 'decimal:2',
        'date_engagement' => 'date',
        'date_signature' => 'date',
        'date_dernier_contact' => 'date',
        'prochain_contact_prevu' => 'date',
        'converted_to_project_at' => 'datetime',
    ];

    /**
     * Entreprise associée à l'investisseur
     */
    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    /**
     * Prospect d'origine si l'investisseur a été converti depuis un prospect
     */
    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    /**
     * Pays de l'investisseur
     */
    public function pays(): BelongsTo
    {
        return $this->belongsTo(Pays::class);
    }
    public function blockages()
    {
        return $this->morphMany(Blockage::class, 'blockable');
    }

    /**
     * Secteur d'activité de l'investisseur
     */
    public function secteur(): BelongsTo
    {
        return $this->belongsTo(Secteur::class);
    }

    /**
     * Utilisateur responsable de l'investisseur
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /**
     * Créateur de l'investisseur
     */
    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Projet associé si l'investisseur a été converti
     */
    public function projet(): HasOne
    {
        return $this->hasOne(Project::class, 'investisseur_id');
    }

    /**
     * Progressions de l'investisseur dans le pipeline
     */
    public function pipelineProgressions(): HasMany
    {
        return $this->hasMany(InvestorPipelineProgression::class, 'investisseur_id');
    }
    public function pipelineStage(): BelongsTo
    {
        return $this->belongsTo(InvestorPipelineStage::class, 'pipeline_stage_id');
    }


    /**
     * Obtenir l'étape actuelle du pipeline
     */
    public function currentStage()
    {
        // Récupérer l'étape non complétée la plus ancienne
        $nonCompleted = $this->pipelineProgressions()
            ->where('completed', false)
            ->orderBy('created_at', 'asc')
            ->first();

        if ($nonCompleted) {
            return $nonCompleted->stage;
        }

        // Si toutes les étapes sont complétées, retourner la dernière
        return $this->pipelineProgressions()
            ->where('completed', true)
            ->orderByDesc('completed_at')
            ->first()?->stage;
    }

    /**
     * Vérifier si l'investisseur peut être converti en projet
     */
    public function canConvertToProject(): bool
{
    // Vérifier si l'investisseur est déjà converti
    if ($this->converted_to_project_at || $this->project_id) {
        return false;
    }

    // Vérifier si une étape finale du pipeline a été complétée
    $hasFinalStageCompleted = $this->pipelineProgressions()
        ->whereHas('stage', function ($query) {
            $query->where('is_final', true);
        })
        ->where('completed', true)
        ->exists();

    return $hasFinalStageCompleted;
}

 
public function initializePipeline($userId = null): bool
{
    try {
        // Si l'investisseur a déjà un pipeline initialisé
        if ($this->pipeline_stage_id) {
            \Log::info("Pipeline déjà initialisé pour l'investisseur #{$this->id}");
            return true;
        }

        // Récupérer toutes les étapes actives du pipeline investisseur
        $stages = InvestorPipelineStage::where('is_active', true)
            ->orderBy('order')
            ->get();

        if ($stages->isEmpty()) {
            \Log::error("Aucune étape de pipeline active trouvée pour les investisseurs");
            return false;
        }

        // Utiliser une transaction pour garantir l'intégrité des données
        return \DB::transaction(function() use ($stages, $userId) {
            // Supprimer les progressions existantes (cas de réinitialisation)
            $this->pipelineProgressions()->delete();
            
            // Créer une progression pour chaque étape
            foreach ($stages as $stage) {
                $this->pipelineProgressions()->create([
                    'stage_id' => $stage->id,
                    'completed' => false,
                    'assigned_to' => $userId ?? $this->responsable_id ?? \Auth::id() ?? 1,
                    'notes' => $stage->order === 1 ? 'Étape initiale créée automatiquement' : null
                ]);
            }
            
            // Définir la première étape comme étape actuelle
            $firstStage = $stages->first();
            $this->update(['pipeline_stage_id' => $firstStage->id]);
            
            \Log::info("Pipeline initialisé avec succès pour investisseur #{$this->id}", [
                'stage_name' => $firstStage->name,
                'stages_count' => $stages->count()
            ]);
            
            return true;
        });
    } catch (\Exception $e) {
        \Log::error("Erreur lors de l'initialisation du pipeline: " . $e->getMessage(), [
            'investisseur_id' => $this->id,
            'exception' => get_class($e),
            'trace' => $e->getTraceAsString()
        ]);
        return false;
    }
}


public function advanceToNextStage($userId, $notes = null): bool
{
    try {
        // Récupérer l'étape actuelle
        $currentStage = $this->pipelineStage;
        
        if (!$currentStage) {
            // Aucune étape actuelle, initialiser le pipeline
            $firstStage = InvestorPipelineStage::where('is_active', true)
                ->orderBy('order')
                ->first();
                
            if (!$firstStage) {
                \Log::error("Aucune étape de pipeline active trouvée");
                return false;
            }
            
            // Créer la première progression
            InvestorPipelineProgression::create([
                'investisseur_id' => $this->id,
                'stage_id' => $firstStage->id,
                'completed' => false,
                'assigned_to' => $userId
            ]);
            
            // Mettre à jour l'étape actuelle
            $this->update(['pipeline_stage_id' => $firstStage->id]);
            
            \Log::info("Pipeline initialisé pour l'investisseur #{$this->id}");
            return true;
        }
        
        return \DB::transaction(function () use ($currentStage, $userId, $notes) {
            // Marquer l'étape actuelle comme complétée
            $progression = $this->pipelineProgressions()
                ->where('stage_id', $currentStage->id)
                ->first();
                
            if ($progression) {
                $progression->update([
                    'completed' => true,
                    'completed_at' => now(),
                    'notes' => $notes ?? $progression->notes
                ]);
            }
            
            // Si c'est l'étape finale, ne pas avancer mais marquer comme complété
            if ($currentStage->is_final) {
                $this->update([
                    'pipeline_completed_at' => now(),
                    'pipeline_completed_by' => $userId
                ]);
                \Log::info("Étape finale atteinte pour l'investisseur #{$this->id}");
                return true;
            }
            
            // Trouver l'étape suivante
            $nextStage = InvestorPipelineStage::where('is_active', true)
                ->where('order', '>', $currentStage->order)
                ->orderBy('order')
                ->first();
                
            if (!$nextStage) {
                \Log::warning("Aucune étape suivante trouvée pour l'investisseur #{$this->id}");
                return false;
            }
            
            // Créer ou mettre à jour la progression pour l'étape suivante
            $nextProgression = $this->pipelineProgressions()
                ->where('stage_id', $nextStage->id)
                ->first();
                
            if (!$nextProgression) {
                $this->pipelineProgressions()->create([
                    'stage_id' => $nextStage->id,
                    'completed' => false,
                    'assigned_to' => $userId
                ]);
            }
            
            // Mettre à jour l'étape actuelle
            $this->update(['pipeline_stage_id' => $nextStage->id]);
            
            \Log::info("Investisseur #{$this->id} avancé à l'étape suivante: {$nextStage->name}");
            return true;
        });
        
    } catch (\Exception $e) {
        \Log::error("Erreur lors de l'avancement de l'investisseur: " . $e->getMessage(), [
            'investisseur_id' => $this->id,
            'trace' => $e->getTraceAsString()
        ]);
        return false;
    }
}

    /**
     * Convertir en projet
     */
    public function convertToProject($userId, array $additionalData = [], $notes = null): ?Project
    {
        if (!$this->canConvertToProject()) {
            return null;
        }

        // Créer le projet
        $projet = Project::create([
            'entreprise_id' => $this->entreprise_id,
            'nom' => $additionalData['nom'] ?? $this->nom,
            'investisseur_id' => $this->id,
            'montant_investissement' => $this->montant_investissement,
            'devise' => $this->devise,
            'pays_id' => $this->pays_id,
            'secteur_id' => $this->secteur_id,
            'date_debut' => now(),
            'date_fin_prevue' => $additionalData['date_fin_prevue'] ?? null,
            'statut' => 'en_cours',
            'responsable_id' => $additionalData['responsable_id'] ?? $this->responsable_id,
            'created_by' => $userId,
            'description' => $additionalData['description'] ?? null,
            'notes_internes' => $notes ?? "Converti depuis l'investisseur #" . $this->id
        ]);

        // Mettre à jour l'investisseur
        $this->update([
            'statut' => 'investi',
            'converted_to_project_at' => now(),
            'project_id' => $projet->id
        ]);

        // Initialiser la première étape du pipeline projet
        $firstStage = ProjectPipelineStage::orderBy('order')->first();

        if ($firstStage) {
            ProjectPipelineProgression::create([
                'projet_id' => $projet->id,
                'stage_id' => $firstStage->id,
                'completed' => false,
                'assigned_to' => $userId
            ]);
        }

        return $projet;
    }
    public function getPipelineStatusDetails()
    {
        $currentStage = $this->currentStage();
        $completedStages = $this->pipelineProgressions()->where('completed', true)->count();

        // Fetch total stages from the pipeline type associated with the current stage
        $totalStages = $currentStage && $currentStage->pipelineType
            ? $currentStage->pipelineType->stages()->count()
            : InvestorPipelineStage::where('is_active', true)->count();

        $hasFinalStage = $this->pipelineProgressions()
            ->whereHas('stage', function ($q) {
                $q->where('is_final', true);
            })
            ->where('completed', true)
            ->exists();

        return [
            'current_stage' => $currentStage?->name,
            'completed_stages' => $completedStages,
            'total_stages' => $totalStages,
            'progression_percentage' => $this->progressionPercentage(),
            'has_completed_final_stage' => $hasFinalStage,
            'can_convert' => $this->canConvertToProject(),
            'statut' => $this->statut,
            'converted_at' => $this->converted_to_project_at
        ];
    }

    /**
     * Obtenir le pourcentage de progression dans le pipeline
     */
   public function progressionPercentage(): int
{
    // ✅ Vérifier si converti au lieu de pipeline_completed_at
    if ($this->converted_to_project_at) {
        return 100;
    }

    $totalStages = InvestorPipelineStage::where('is_active', true)->count();

    if ($totalStages === 0) {
        return 0;
    }

    $completedStages = $this->pipelineProgressions()
        ->where('completed', true)
        ->count();

    return (int) round(($completedStages / $totalStages) * 100);
}

    /**
     * Obtenir l'historique des étapes franchies
     */
    public function getStageHistory()
    {
        return $this->pipelineProgressions()
            ->with(['stage', 'assignedTo'])
            ->where('completed', true)
            ->orderBy('completed_at')
            ->get()
            ->map(function ($progression) {
                return [
                    'stage' => $progression->stage->name,
                    'completed_at' => $progression->completed_at,
                    'completed_by' => $progression->assignedTo->name ?? 'N/A',
                    'notes' => $progression->notes
                ];
            });
    }
}
