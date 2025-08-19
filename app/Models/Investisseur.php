<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Investisseur extends Model
{
    use SoftDeletes;

    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array
     */
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
        'project_id'
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
        if (in_array($this->statut, ['investi', 'suspendu', 'inactif']) || $this->converted_to_project_at !== null) {
            return false;
        }
        
        // Un investisseur peut être converti s'il a complété une étape finale du pipeline
        return $this->pipelineProgressions()
                    ->whereHas('stage', function($q) {
                        $q->where('is_final', true);
                    })
                    ->where('completed', true)
                    ->exists();
    }

    /**
     * Avancer à l'étape suivante du pipeline
     */
    public function advanceToNextStage($userId, $notes = null): ?InvestorPipelineProgression
    {
        $currentStage = $this->currentStage();
        
        if (!$currentStage) {
            // Obtenir la première étape du pipeline par défaut
            $firstStage = InvestorPipelineStage::orderBy('order')->first();
            if (!$firstStage) return null;
            
            return InvestorPipelineProgression::create([
                'investisseur_id' => $this->id,
                'stage_id' => $firstStage->id,
                'completed' => false,
                'assigned_to' => $userId,
                'notes' => $notes
            ]);
        }
        
        // Marquer l'étape actuelle comme complétée
        $currentProgression = $this->pipelineProgressions()
                                   ->where('stage_id', $currentStage->id)
                                   ->first();
        
        if ($currentProgression && !$currentProgression->completed) {
            $currentProgression->update([
                'completed' => true,
                'completed_at' => now(),
                'notes' => $notes ?: $currentProgression->notes
            ]);
        }
        
        // Trouver l'étape suivante
        $nextStage = InvestorPipelineStage::where('pipeline_type_id', $currentStage->pipeline_type_id)
                                        ->where('order', '>', $currentStage->order)
                                        ->orderBy('order')
                                        ->first();
        
        if (!$nextStage) return null;
        
        // Créer la progression pour l'étape suivante
        return InvestorPipelineProgression::create([
            'investisseur_id' => $this->id,
            'stage_id' => $nextStage->id,
            'completed' => false,
            'assigned_to' => $userId
        ]);
    }

    /**
     * Convertir en projet
     */
    public function convertToProject($userId, array $additionalData = [], $notes = null): ?Projet
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

    /**
     * Obtenir le pourcentage de progression dans le pipeline
     */
    public function progressionPercentage(): int
    {
        $currentStage = $this->currentStage();
        if (!$currentStage) return 0;
        
        $pipelineType = $currentStage->pipelineType;
        $totalStages = $pipelineType->stages()->count();
        
        if ($totalStages === 0) return 0;
        
        $completedStages = $this->pipelineProgressions()
                               ->where('completed', true)
                               ->count();
        
        return min(100, round(($completedStages / $totalStages) * 100));
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
                   ->map(function($progression) {
                       return [
                           'stage' => $progression->stage->name,
                           'completed_at' => $progression->completed_at,
                           'completed_by' => $progression->assignedTo->name ?? 'N/A',
                           'notes' => $progression->notes
                       ];
                   });
    }
}