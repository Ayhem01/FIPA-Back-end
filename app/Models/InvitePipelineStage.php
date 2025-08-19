<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvitePipelineStage extends Model
{
    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'pipeline_type_id',
        'name',
        'slug',
        'description',
        'order',
        'is_final',
        'color',
        'status',
        'is_active',
        'conversion_eligible',
        'created_by'
    ];

    /**
     * Les attributs à caster.
     *
     * @var array
     */
    protected $casts = [
        'is_final' => 'boolean',
        'is_active' => 'boolean',
        'conversion_eligible' => 'boolean',
        'order' => 'integer'
    ];

    /**
     * Le type de pipeline auquel cette étape appartient
     */
    public function pipelineType(): BelongsTo
    {
        return $this->belongsTo(InvitePipelineType::class, 'pipeline_type_id');
    }

    /**
     * L'utilisateur qui a créé cette étape
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Les progressions associées à cette étape
     */
    public function progressions(): HasMany
    {
        return $this->hasMany(InvitePipelineProgression::class, 'stage_id');
    }

    /**
     * Les invités actuellement à cette étape
     */
    public function activeInvites()
    {
        return Invite::whereHas('pipelineProgressions', function($query) {
            $query->where('stage_id', $this->id)
                  ->where('completed', false);
        });
    }

    /**
     * Les invités qui ont complété cette étape
     */
    public function completedInvites()
    {
        return Invite::whereHas('pipelineProgressions', function($query) {
            $query->where('stage_id', $this->id)
                  ->where('completed', true);
        });
    }

    /**
     * Obtenir la prochaine étape dans ce pipeline
     */
    public function nextStage()
    {
        return self::where('pipeline_type_id', $this->pipeline_type_id)
                  ->where('order', '>', $this->order)
                  ->where('is_active', true)
                  ->orderBy('order')
                  ->first();
    }

    /**
     * Obtenir l'étape précédente dans ce pipeline
     */
    public function previousStage()
    {
        return self::where('pipeline_type_id', $this->pipeline_type_id)
                  ->where('order', '<', $this->order)
                  ->where('is_active', true)
                  ->orderBy('order', 'desc')
                  ->first();
    }

    /**
     * Vérifier si cette étape est la première du pipeline
     */
    public function isFirstStage(): bool
    {
        return !self::where('pipeline_type_id', $this->pipeline_type_id)
                   ->where('order', '<', $this->order)
                   ->where('is_active', true)
                   ->exists();
    }

    /**
     * Vérifier si cette étape est la dernière du pipeline
     */
    public function isLastStage(): bool
    {
        return !self::where('pipeline_type_id', $this->pipeline_type_id)
                   ->where('order', '>', $this->order)
                   ->where('is_active', true)
                   ->exists();
    }

    /**
     * Déplacer cette étape vers le haut dans l'ordre du pipeline
     */
    public function moveUp(): bool
    {
        if ($this->isFirstStage()) {
            return false;
        }

        $previousStage = $this->previousStage();
        
        if (!$previousStage) {
            return false;
        }

        $currentOrder = $this->order;
        $previousOrder = $previousStage->order;

        $this->update(['order' => $previousOrder]);
        $previousStage->update(['order' => $currentOrder]);

        return true;
    }

    /**
     * Déplacer cette étape vers le bas dans l'ordre du pipeline
     */
    public function moveDown(): bool
    {
        if ($this->isLastStage()) {
            return false;
        }

        $nextStage = $this->nextStage();
        
        if (!$nextStage) {
            return false;
        }

        $currentOrder = $this->order;
        $nextOrder = $nextStage->order;

        $this->update(['order' => $nextOrder]);
        $nextStage->update(['order' => $currentOrder]);

        return true;
    }

    /**
     * Obtenir le nombre d'invités actuellement à cette étape
     */
    public function getActiveInvitesCountAttribute(): int
    {
        return $this->progressions()
                   ->where('completed', false)
                   ->count();
    }

    /**
     * Obtenir la durée moyenne passée dans cette étape
     */
    public function getAverageDurationAttribute(): float
    {
        $completedProgressions = $this->progressions()
                                     ->where('completed', true)
                                     ->get();
        
        if ($completedProgressions->isEmpty()) {
            return 0;
        }

        $totalDays = $completedProgressions->sum(function ($progression) {
            return $progression->created_at->diffInDays($progression->completed_at);
        });

        return round($totalDays / $completedProgressions->count(), 1);
    }

    /**
     * Obtenir le taux de conversion vers l'étape suivante
     */
    public function getConversionRateAttribute(): float
    {
        $total = $this->progressions()->count();
        
        if ($total === 0) {
            return 0;
        }

        $completed = $this->progressions()->where('completed', true)->count();
        
        return round(($completed / $total) * 100, 1);
    }

    /**
     * Obtenir les invités éligibles à la conversion en prospects
     */
    public function getConversionEligibleInvites()
    {
        if (!$this->conversion_eligible) {
            return Invite::whereRaw('1 = 0'); // Retourne une requête vide
        }
        
        return Invite::whereHas('pipelineProgressions', function($query) {
            $query->where('stage_id', $this->id)
                  ->where('completed', true);
        })->whereDoesntHave('prospect')
          ->orderBy('updated_at', 'desc');
    }

    /**
     * Convertir les invités éligibles en prospects
     */
    public function convertEligibleInvites(?int $userId = null): array
    {
        if (!$this->conversion_eligible) {
            return [
                'success_count' => 0,
                'failed_count' => 0,
                'converted' => [],
                'failed' => [],
                'reason' => 'Stage not eligible for conversion'
            ];
        }
        
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
     * Vérifier si l'étape correspond à un statut spécifique d'invitation
     */
    public function matchesInviteStatus(string $status): bool
    {
        $statusMap = [
            'en_attente' => ['Contact initial', 'Initial Contact'],
            'envoyee' => ['Invitation envoyée', 'Invitation Sent'],
            'confirmee' => ['Invitation confirmée', 'Invitation Confirmed'],
            'participee' => ['Participation', 'Attendance', 'Participated'],
            'refusee' => ['Refusé', 'Declined'],
            'absente' => ['Absent', 'No-show']
        ];
        
        if (!isset($statusMap[$status])) {
            return false;
        }
        
        return in_array($this->name, $statusMap[$status]) || 
               stripos($this->slug, str_replace('_', '-', $status)) !== false;
    }
    
    /**
     * Synchroniser automatiquement le statut des invités avec cette étape
     */
    public function syncInviteStatuses(): array
    {
        $updated = [];
        $failed = [];
        $statuses = ['en_attente', 'envoyee', 'confirmee', 'participee', 'refusee', 'absente'];
        
        foreach ($statuses as $status) {
            if ($this->matchesInviteStatus($status)) {
                $invites = $this->activeInvites()->where('statut', '!=', $status)->get();
                
                foreach ($invites as $invite) {
                    try {
                        $invite->update(['statut' => $status]);
                        $updated[] = [
                            'invite_id' => $invite->id, 
                            'name' => $invite->prenom . ' ' . $invite->nom,
                            'old_status' => $invite->getOriginal('statut'),
                            'new_status' => $status
                        ];
                    } catch (\Exception $e) {
                        $failed[] = [
                            'invite_id' => $invite->id,
                            'name' => $invite->prenom . ' ' . $invite->nom,
                            'error' => $e->getMessage()
                        ];
                    }
                }
                
                break;
            }
        }
        
        return [
            'success_count' => count($updated),
            'failed_count' => count($failed),
            'updated' => $updated,
            'failed' => $failed
        ];
    }
}