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
        'converted_to_id',
        'pipeline_stage_id',
        'pipeline_completed_at',
        'pipeline_completed_by',
        'is_converted'
    ];

    // [Autres attributs existants]

    /**
     * L'étape de pipeline actuelle de ce prospect
     */
    public function pipelineStage(): BelongsTo
    {
        return $this->belongsTo(ProspectPipelineStage::class, 'pipeline_stage_id');
    }

    /**
     * Les progressions de pipeline pour ce prospect
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
        return $this->pipelineStage;
    }

    public function entreprise()
{
    return $this->belongsTo(Entreprise::class, 'entreprise_id');
}
    public function invite()
    {
    return $this->belongsTo(Invite::class, 'invite_id');
    }
    public function pays()
    {
    return $this->belongsTo(Pays::class, 'pays_id');
    }
    public function secteur()
    {
    return $this->belongsTo(Secteur::class, 'secteur_id');
    }
    public function responsable()
    {
    return $this->belongsTo(User::class, 'responsable_id');
    }
    public function createur()
    {
    return $this->belongsTo(User::class, 'created_by');
    }
    public function investisseur()
    {
    return $this->belongsTo(Investisseur::class, 'investisseur_id');
    }
    /**
     * Vérifier si le prospect peut être converti en investisseur
     */
    public function canConvertToInvestor(): bool
    {
        // Vérifier si déjà converti
        if ($this->statut === 'converti' || $this->converted_at) {
            return false;
        }
        
        // Vérifier si on est dans l'étape finale
        $currentStage = $this->pipelineStage;
        
        if (!$currentStage) {
            return false;
        }
        
        // ✅ NOUVELLE LOGIQUE SIMPLIFIÉE : juste vérifier si c'est l'étape finale
        return $currentStage->is_final === true;
    }
    
    

// public function initializePipeline($userId = null): bool
// {
//     // Obtenir la première étape active
//     $firstStage = ProspectPipelineStage::where('is_active', true)
//                                      ->orderBy('order')
//                                      ->first();
    
//     if (!$firstStage) {
//         \Log::error("Aucune étape de pipeline active trouvée pour les prospects");
//         return false;
//     }
    
//     // Mettre à jour le prospect avec l'étape initiale
//     $this->update([
//         'pipeline_stage_id' => $firstStage->id
//     ]);
    
//     // Créer la première progression
//     ProspectPipelineProgression::create([
//         'prospect_id' => $this->id,
//         'stage_id' => $firstStage->id,
//         'completed' => false,
//         'assigned_to' => $userId ?? $this->responsable_id ?? auth()->id()
//     ]);
    
//     return true;
// }
public function initializePipeline($userId = null)
{
    $firstStage = ProspectPipelineStage::orderBy('ordre', 'asc')->first();

    if (!$firstStage) {
        \Log::error("Aucune étape de pipeline définie en base.");
        return false;
    }

    $this->update(['pipeline_stage_id' => $firstStage->id]);

    ProspectPipelineProgression::create([
        'prospect_id' => $this->id,
        'stage_id' => $firstStage->id,
        'completed' => false,
        'assigned_to' => $userId ?? $this->responsable_id,
    ]);

    return true;
}


    /**
     * Initialiser le pipeline pour ce prospect
     */
    // public function initializePipeline($userId = null): bool
    // {
    //     // Si le pipeline est déjà initialisé, ne rien faire
    //     if ($this->pipeline_stage_id) {
    //         return true;
    //     }
    
    //     try {
    //         // Utiliser une transaction pour garantir la cohérence
    //         return \DB::transaction(function() use ($userId) {
    //             // Obtenir la première étape active
    //             $firstStage = ProspectPipelineStage::where('is_active', true)
    //                                               ->orderBy('order')
    //                                               ->first();
                
    //             if (!$firstStage) {
    //                 \Log::error("Aucune étape de pipeline active trouvée pour les prospects");
    //                 return false;
    //             }
                
    //             // Créer la progression
    //             ProspectPipelineProgression::create([
    //                 'prospect_id' => $this->id,
    //                 'stage_id' => $firstStage->id,
    //                 'completed' => false,
    //                 'assigned_to' => $userId ?? $this->responsable_id ?? auth()->id()
    //             ]);
                
    //             // Mettre à jour l'étape actuelle
    //             $this->update(['pipeline_stage_id' => $firstStage->id]);
                
    //             \Log::info("Pipeline initialisé avec succès pour prospect #{$this->id}", [
    //                 'stage_name' => $firstStage->name
    //             ]);
                
    //             return true;
    //         });
    //     } catch (\Exception $e) {
    //         \Log::error("Erreur lors de l'initialisation du pipeline: " . $e->getMessage(), [
    //             'prospect_id' => $this->id,
    //             'exception' => get_class($e),
    //             'trace' => $e->getTraceAsString()
    //         ]);
    //         return false;
    //     }
    // }

    /**
     * Calculer le pourcentage de progression dans le pipeline
     */
    // public function progressionPercentage(): int
    // {
    //     $totalStages = ProspectPipelineStage::where('is_active', true)->count();
        
    //     if ($totalStages === 0) return 0;
        
    //     // Compter les stages complétés
    //     $completedStages = $this->pipelineProgressions()
    //                         ->where('completed', true)
    //                         ->count();
        
    //     // Calculer le pourcentage
    //     $percentage = (int) round(($completedStages / $totalStages) * 100);
        
    //     return min(100, $percentage);
    // }

    

    public function advanceToNextStage($userId = null, $notes = null): bool
{
    try {
        $currentStage = $this->pipelineStage;

        // 🚀 Initialisation si aucune étape
        if (!$currentStage) {
            $firstStage = ProspectPipelineStage::where('is_active', true)
                ->orderBy('order')
                ->first();

            if (!$firstStage) {
                \Log::error("Aucune étape de pipeline active trouvée pour le prospect #{$this->id}");
                return false;
            }

            // Créer la première progression
            $this->pipelineProgressions()->create([
                'stage_id'    => $firstStage->id,
                'completed'   => false,
                'assigned_to' => $userId ?? $this->responsable_id
            ]);

            // Mettre à jour l’étape actuelle
            $this->update(['pipeline_stage_id' => $firstStage->id]);

            \Log::info("Pipeline initialisé pour le prospect #{$this->id}");
            return true;
        }

        // 🚀 Transaction pour l’avancement
        return \DB::transaction(function () use ($currentStage, $userId, $notes) {
            // Marquer l’étape actuelle comme complétée
            $progression = $this->pipelineProgressions()
                ->where('stage_id', $currentStage->id)
                ->first();

            if ($progression) {
                $progression->update([
                    'completed'     => true,
                    'completed_at'  => now(),
                    'notes'         => $notes ?? $progression->notes
                ]);
            }

            // Si étape finale → marquer pipeline terminé
            if ($currentStage->is_final) {
                $this->update([
                    'pipeline_completed_at' => now(),
                    'pipeline_completed_by' => $userId
                ]);
                \Log::info("Étape finale atteinte pour le prospect #{$this->id}");
                return true;
            }

            // 🚀 Trouver étape suivante
            $nextStage = ProspectPipelineStage::where('is_active', true)
                ->where('order', '>', $currentStage->order)
                ->orderBy('order')
                ->first();

            if (!$nextStage) {
                \Log::warning("Aucune étape suivante trouvée pour le prospect #{$this->id}");
                return false;
            }

            // Créer progression pour l’étape suivante si inexistante
            $nextProgression = $this->pipelineProgressions()
                ->where('stage_id', $nextStage->id)
                ->first();

            if (!$nextProgression) {
                $this->pipelineProgressions()->create([
                    'stage_id'    => $nextStage->id,
                    'completed'   => false,
                    'assigned_to' => $userId ?? $this->responsable_id
                ]);
            }

            // Mettre à jour l’étape actuelle
            $this->update(['pipeline_stage_id' => $nextStage->id]);

            \Log::info("Prospect #{$this->id} avancé à l’étape suivante: {$nextStage->name}");
            return true;
        });
    } catch (\Exception $e) {
        \Log::error("Erreur lors de l’avancement du prospect: " . $e->getMessage(), [
            'prospect_id' => $this->id,
            'trace'       => $e->getTraceAsString()
        ]);
        return false;
    }
}


    public function getIsConvertedAttribute()
    {
        return $this->statut === 'converti' && !is_null($this->converted_at);
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

// public function initializePipeline($userId = null): bool
// {
//     // Récupérer le type de pipeline par défaut
//     $defaultPipelineType = ProspectPipelineType::where('is_default', true)->first();
    
//     if (!$defaultPipelineType) {
//         return false;
//     }
    
//     // Récupérer la première étape du pipeline
//     $firstStage = ProspectPipelineStage::where('pipeline_type_id', $defaultPipelineType->id)
//                                       ->orderBy('order')
//                                       ->first();
    
//     if (!$firstStage) {
//         return false;
//     }
    
//     // Mettre à jour le prospect avec le pipeline et l'étape initiale
//     $this->update([
//         'pipeline_type_id' => $defaultPipelineType->id,
//         'pipeline_stage_id' => $firstStage->id
//     ]);
    
//     // Créer la première progression
//     ProspectPipelineProgression::create([
//         'prospect_id' => $this->id,
//         'stage_id' => $firstStage->id,
//         'completed' => false,
//         'assigned_to' => $userId ?? auth()->id()
//     ]);
    
//     return true;
// }

    /**
     * Obtenir le pourcentage de progression dans le pipeline
     */
//     public function progressionPercentage(): int
// {
//     $currentStage = $this->currentStage();
//     if (!$currentStage) return 0;
    
//     $pipelineType = $currentStage->pipelineType;
//     $totalStages = $pipelineType->stages()->count();
    
//     if ($totalStages === 0) return 0;
    
//     $completedStages = $this->pipelineProgressions()
//                           ->where('completed', true)
//                           ->count();
    
//     // Si l'étape finale est complétée, retourner 100%
//     $finalStageCompleted = $this->pipelineProgressions()
//                               ->whereHas('stage', function($q) {
//                                   $q->where('is_final', true);
//                               })
//                               ->where('completed', true)
//                               ->exists();
    
//     if ($finalStageCompleted) {
//         return 100;
//     }
    
//     return (int) round(($completedStages / $totalStages) * 100);
// }

public function getPipelineStatusDetails()
{
    if (!$this->pipeline_stage_id) {
        return null;
    }

    $currentStage = $this->pipelineStage;
    $allStages = ProspectPipelineStage::getAllStagesInOrder();
    
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


public function canAdvanceToNextStage()
{
    if (!$this->pipeline_stage_id) {
        \Log::warning("Le prospect #{$this->id} n'a pas d'étape de pipeline initialisée.");
        return false;
    }

    $currentStage = $this->pipelineStage;
    if (!$currentStage) {
        \Log::error("Étape actuelle introuvable pour le prospect #{$this->id} avec pipeline_stage_id #{$this->pipeline_stage_id}.");
        return false;
    }

    if ($currentStage->is_final) {
        \Log::info("Le prospect #{$this->id} est déjà à l'étape finale.");
        return false;
    }

    return true;
}

public function nextStage()
{
    $currentStage = $this->pipelineStage;

    if (!$currentStage) {
        return null;
    }

    return ProspectPipelineStage::where('is_active', true)
        ->where('order', '>', $currentStage->order)
        ->orderBy('order')
        ->first();
}

public function getNextStage()
{
    if (!$this->pipeline_stage_id) {
        \Log::warning("Le prospect #{$this->id} n'a pas d'étape de pipeline initialisée.");
        return null;
    }

    $currentStage = $this->pipelineStage;
    if (!$currentStage) {
        \Log::error("Étape actuelle introuvable pour le prospect #{$this->id} avec pipeline_stage_id #{$this->pipeline_stage_id}.");
        return null;
    }

    $nextStage = ProspectPipelineStage::where('order', '>', $currentStage->order)
        ->orderBy('order', 'asc')
        ->first();

    if (!$nextStage) {
        \Log::info("Aucune étape suivante trouvée après l'étape #{$currentStage->id} pour le prospect #{$this->id}.");
        return null;
    }

    return $nextStage;
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

    return ProspectPipelineStage::where('order', '<', $currentStage->order)
        ->orderBy('order', 'desc')
        ->first();
}
public function progressionPercentage(): int
{
    // Si le pipeline est marqué comme terminé, retourner 100%
    if ($this->pipeline_completed_at) {
        return 100;
    }
    
    $totalStages = ProspectPipelineStage::where('is_active', true)->count();
    
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
}