<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Prospect extends Model
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
        'invite_id',
        'email',
        'telephone',
        'adresse',
        'pays_id',
        'secteur_id',
        'statut',
        'responsable_id',
        'created_by',
        'description',
        'notes_internes',
        'valeur_potentielle',
        'devise',
        'date_dernier_contact',
        'prochain_contact_prevu',
        'converted_at',
        'converted_to_id'
    ];

    /**
     * Les attributs à caster.
     *
     * @var array
     */
    protected $casts = [
        'valeur_potentielle' => 'decimal:2',
        'date_dernier_contact' => 'date',
        'prochain_contact_prevu' => 'date',
        'converted_at' => 'datetime',
    ];

    /**
     * Entreprise associée au prospect
     */
    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    /**
     * Invité d'origine si le prospect a été converti depuis un invité
     */
    public function invite(): BelongsTo
    {
        return $this->belongsTo(Invite::class);
    }

    /**
     * Pays du prospect
     */
    public function pays(): BelongsTo
    {
        return $this->belongsTo(Pays::class);
    }

    /**
     * Secteur d'activité du prospect
     */
    public function secteur(): BelongsTo
    {
        return $this->belongsTo(Secteur::class);
    }

    /**
     * Utilisateur responsable du prospect
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /**
     * Créateur du prospect
     */
    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Investisseur associé si le prospect a été converti
     */
    public function investisseur(): HasOne
    {
        return $this->hasOne(Investisseur::class);
    }

    /**
     * Progressions du prospect dans le pipeline
     */
    public function pipelineProgressions(): HasMany
    {
        return $this->hasMany(ProspectPipelineProgression::class, 'prospect_id');
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
     * Vérifier si le prospect peut être converti en investisseur
     */
    public function canConvertToInvestor(): bool
    {
        // Vérifier si le prospect est déjà converti
        if ($this->statut === 'converti' || $this->converted_at !== null) {
            return false;
        }
        
        // Un prospect peut être converti s'il a complété une étape finale du pipeline
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
    public function advanceToNextStage($userId, $notes = null): ?ProspectPipelineProgression
    {
        $currentStage = $this->currentStage();
        
        if (!$currentStage) {
            // Obtenir la première étape du pipeline par défaut
            $firstStage = ProspectPipelineStage::orderBy('order')->first();
            if (!$firstStage) return null;
            
            return ProspectPipelineProgression::create([
                'prospect_id' => $this->id,
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
        $nextStage = ProspectPipelineStage::where('pipeline_type_id', $currentStage->pipeline_type_id)
                                        ->where('order', '>', $currentStage->order)
                                        ->orderBy('order')
                                        ->first();
        
        if (!$nextStage) return null;
        
        // Créer la progression pour l'étape suivante
        return ProspectPipelineProgression::create([
            'prospect_id' => $this->id,
            'stage_id' => $nextStage->id,
            'completed' => false,
            'assigned_to' => $userId
        ]);
    }

    /**
     * Convertir en investisseur
     */
    public function convertToInvestor($userId, array $additionalData = [], $notes = null): ?Investisseur
    {
        if (!$this->canConvertToInvestor()) {
            return null;
        }
        
        // Créer l'investisseur
        $investisseur = Investisseur::create([
            'entreprise_id' => $this->entreprise_id,
            'nom' => $additionalData['nom'] ?? $this->nom,
            'prospect_id' => $this->id,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'adresse' => $this->adresse,
            'pays_id' => $this->pays_id,
            'secteur_id' => $this->secteur_id,
            'responsable_id' => $additionalData['responsable_id'] ?? $this->responsable_id,
            'created_by' => $userId,
            'notes_internes' => $notes ?? "Converti depuis le prospect #" . $this->id
        ]);
        
        // Mettre à jour le prospect
        $this->update([
            'statut' => 'converti',
            'converted_at' => now(),
            'converted_to_id' => $investisseur->id
        ]);
        
        // Initialiser la première étape du pipeline investisseur
        $firstStage = InvestorPipelineStage::orderBy('order')->first();
        
        if ($firstStage) {
            InvestorPipelineProgression::create([
                'investisseur_id' => $investisseur->id,
                'stage_id' => $firstStage->id,
                'completed' => false,
                'assigned_to' => $userId
            ]);
        }
        
        return $investisseur;
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
}