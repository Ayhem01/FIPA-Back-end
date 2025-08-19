<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\Traits\HasPipelineConversions;
use App\Mail\InvitationMail;

class Invite extends Model
{
    use HasFactory, SoftDeletes, HasPipelineConversions;

    /**
     * Les attributs qui sont mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'entreprise_id',
        'action_id',
        'etape_id',
        'nom',
        'prenom',
        'email',
        'telephone',
        'fonction',
        'type_invite', // 'interne', 'externe'
        'statut', // 'en_attente', 'envoyee', 'confirmee', 'refusee', 'participee', 'absente'
        'suivi_requis',
        'date_invitation',
        'date_evenement',
        'commentaires',
        'proprietaire_id',
        'pays_id',
        'secteur_id',
        'potentiel', // 'faible', 'moyen', 'élevé'
        'token', // Token unique pour confirmation
        'date_rappel',
        'date_conversion',
        'pipeline_type_id',
        'pipeline_stage_id'
    ];

    /**
     * Les attributs à caster.
     *
     * @var array
     */
    protected $casts = [
        'suivi_requis' => 'boolean',
        'date_invitation' => 'datetime',
        'date_evenement' => 'datetime',
        'date_rappel' => 'datetime',
        'date_conversion' => 'datetime',
    ];

    /**
     * Les statuts possibles pour une invitation
     */
    const STATUT_EN_ATTENTE = 'en_attente';
    const STATUT_ENVOYEE = 'envoyee';
    const STATUT_CONFIRMEE = 'confirmee';
    const STATUT_REFUSEE = 'refusee';
    const STATUT_DETAILS_ENVOYES = 'details_envoyes';
    const STATUT_PARTICIPATION_CONFIRME = 'participation_confirmee';
    const STATUT_PARTICIPATION_SANS_SUIVI = 'participation_sans_suivi';
    const STATUT_ABSENTE = 'absente';
    const STATUT_AUCUNE_REPONSE = 'aucune_reponse';
   


    /**
     * Les types d'invités
     */
    const TYPE_INTERNE = 'interne';
    const TYPE_EXTERNE = 'externe';

    /**
     * L'entreprise associée à cette invitation
     */
    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class);
    }

    /**
     * L'action associée à cette invitation
     */
    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class);
    }

    /**
     * L'étape associée à cette invitation
     */
    public function etape(): BelongsTo
    {
        return $this->belongsTo(Etape::class);
    }

    /**
     * Le propriétaire de l'invitation
     */
    public function proprietaire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proprietaire_id');
    }

    /**
     * Le pays de l'invité
     */
    public function pays(): BelongsTo
    {
        return $this->belongsTo(Pays::class);
    }

    /**
     * Le secteur d'activité de l'invité
     */
    public function secteur(): BelongsTo
    {
        return $this->belongsTo(Secteur::class);
    }

    /**
     * Le prospect créé à partir de cet invité (si converti)
     */
    public function prospect(): HasOne
    {
        return $this->hasOne(Prospect::class, 'invite_id');
    }

    /**
     * Le type de pipeline associé à cet invité
     */
    public function pipelineType(): BelongsTo
    {
        return $this->belongsTo(InvitePipelineType::class, 'pipeline_type_id');
    }

    /**
     * L'étape de pipeline actuelle de cet invité
     */
    public function pipelineStage(): BelongsTo
    {
        return $this->belongsTo(InvitePipelineStage::class, 'pipeline_stage_id');
    }

    /**
     * Les progressions de pipeline pour cet invité
     */
    public function pipelineProgressions(): HasMany
    {
        return $this->hasMany(InvitePipelineProgression::class, 'invite_id');
    }

    /**
     * Vérifie si l'invité a été converti en prospect
     */
    public function isConvertedToProspect(): bool
    {
        return $this->prospect()->exists();
    }

 
/**
 * Vérifie si l'invitation est en attente d'envoi
 */
public function isEnAttente(): bool
{
    return $this->statut === self::STATUT_EN_ATTENTE;
}

/**
 * Vérifie si l'invitation a été envoyée
 */
public function isEnvoyee(): bool
{
    return in_array($this->statut, [
        self::STATUT_ENVOYEE,
        self::STATUT_DETAILS_ENVOYES
    ]);
}

/**
 * Vérifie si l'invitation a été confirmée
 */
public function isConfirmee(): bool
{
    return in_array($this->statut, [
        self::STATUT_CONFIRMEE,
        self::STATUT_PARTICIPATION_CONFIRME
    ]);
}

/**
 * Vérifie si l'invitation a été refusée
 */
public function isRefusee(): bool
{
    return $this->statut === self::STATUT_REFUSEE;
}

/**
 * Vérifie si l'invité a participé
 */
public function isParticipee(): bool
{
    return in_array($this->statut, [
        self::STATUT_PARTICIPATION_CONFIRME,
        self::STATUT_PARTICIPATION_SANS_SUIVI
    ]);
}

/**
 * Vérifie si l'invité était absent
 */
public function isAbsente(): bool
{
    return $this->statut === self::STATUT_ABSENTE;
}

/**
 * Vérifie si l'invité n'a pas répondu
 */
public function isAucuneReponse(): bool
{
    return $this->statut === self::STATUT_AUCUNE_REPONSE;
}

/**
 * Marquer comme envoyée
 */
public function markAsSent(): bool
{
    if ($this->statut === self::STATUT_EN_ATTENTE) {
        return $this->update([
            'statut' => self::STATUT_ENVOYEE,
            'date_invitation' => now()
        ]);
    }
    return false;
}

/**
 * Marquer comme détails envoyés
 */
public function markAsDetailsSent(): bool
{
    if (in_array($this->statut, [self::STATUT_ENVOYEE, self::STATUT_CONFIRMEE])) {
        return $this->update([
            'statut' => self::STATUT_DETAILS_ENVOYES
        ]);
    }
    return false;
}

/**
 * Marquer comme confirmée
 */
public function markAsConfirmed(): bool
{
    if (in_array($this->statut, [self::STATUT_ENVOYEE, self::STATUT_DETAILS_ENVOYES])) {
        return $this->update([
            'statut' => self::STATUT_CONFIRMEE
        ]);
    }
    return false;
}

/**
 * Marquer comme refusée
 */
public function markAsDeclined(): bool
{
    if (in_array($this->statut, [
        self::STATUT_ENVOYEE,
        self::STATUT_CONFIRMEE,
        self::STATUT_DETAILS_ENVOYES
    ])) {
        return $this->update([
            'statut' => self::STATUT_REFUSEE
        ]);
    }
    return false;
}

/**
 * Marquer comme participation confirmée
 */
public function markAsAttended(): bool
{
    if (in_array($this->statut, [
        self::STATUT_ENVOYEE,
        self::STATUT_CONFIRMEE,
        self::STATUT_DETAILS_ENVOYES
    ])) {
        return $this->update([
            'statut' => self::STATUT_PARTICIPATION_CONFIRME
        ]);
    }
    return false;
}

/**
 * Marquer comme participation sans suivi
 */
public function markAsAttendedWithoutFollowUp(): bool
{
    if (in_array($this->statut, [
        self::STATUT_ENVOYEE,
        self::STATUT_CONFIRMEE,
        self::STATUT_DETAILS_ENVOYES,
        self::STATUT_PARTICIPATION_CONFIRME
    ])) {
        return $this->update([
            'statut' => self::STATUT_PARTICIPATION_SANS_SUIVI
        ]);
    }
    return false;
}

/**
 * Marquer comme absent
 */
public function markAsAbsent(): bool
{
    if (in_array($this->statut, [
        self::STATUT_ENVOYEE,
        self::STATUT_CONFIRMEE,
        self::STATUT_DETAILS_ENVOYES
    ])) {
        return $this->update([
            'statut' => self::STATUT_ABSENTE
        ]);
    }
    return false;
}

/**
 * Marquer comme sans réponse
 */
public function markAsNoResponse(): bool
{
    if (in_array($this->statut, [self::STATUT_ENVOYEE, self::STATUT_DETAILS_ENVOYES])) {
        return $this->update([
            'statut' => self::STATUT_AUCUNE_REPONSE
        ]);
    }
    return false;
}
    /**
     * Générer un token unique
     */
    public function generateToken(): string
    {
        $token = \Str::random(64);
        $this->update(['token' => $token]);
        return $token;
    }

    

/**
 * Envoyer l'invitation par email
 */

public function sendInvitation(): bool
{
    if (!$this->email) {
        \Log::warning("Tentative d'envoi d'invitation sans email: Invite #{$this->id}");
        return false;
    }

    try {
        // Générer un token s'il n'existe pas
        if (!$this->token) {
            $this->generateToken();
        }

        // Vérifier et charger la relation action si nécessaire
        if (!$this->relationLoaded('action')) {
            $this->load('action');
        }

        // Vérifier que l'action existe
        if (!$this->action) {
            \Log::warning("Invitation #{$this->id} n'a pas d'action associée");
            return false;
        }

        // Créer les données pour l'email
        $mailData = [
            'invite' => $this,
            'action' => $this->action,
            'confirmUrl' => route('invitations.confirm', ['token' => $this->token]),
            'declineUrl' => route('invitations.decline', ['token' => $this->token]),
        ];

        // Envoyer l'email
        Mail::to($this->email)->send(new InvitationMail($mailData));

        // Marquer comme envoyée
        return $this->markAsSent();
    } catch (\Exception $e) {
        \Log::error("Erreur lors de l'envoi de l'invitation #{$this->id}: " . $e->getMessage(), [
            'invite_id' => $this->id,
            'email' => $this->email,
            'trace' => $e->getTraceAsString()
        ]);
        return false;
    }
}

    /**
     * Convertir l'invité en prospect
     */
    public function convertToProspect($userId = null): ?Prospect
    {
        // Vérifier si l'invité est déjà converti
        if ($this->isConvertedToProspect()) {
            return $this->prospect;
        }
    
        try {
            // Utiliser une transaction pour garantir l'intégrité des données
            return \DB::transaction(function () use ($userId) {
                // Créer un prospect basé sur cet invité
                $prospect = Prospect::create([
                    'entreprise_id' => $this->entreprise_id,
                    'nom' => $this->nom . ' ' . $this->prenom,
                    'invite_id' => $this->id,
                    'email' => $this->email,
                    'telephone' => $this->telephone,
                    'pays_id' => $this->pays_id,
                    'secteur_id' => $this->secteur_id,
                    'statut' => 'nouveau',
                    'responsable_id' => $userId ?? $this->proprietaire_id,
                    'created_by' => $userId ?? $this->proprietaire_id,
                    'description' => "Converti depuis l'invitation à " . ($this->action ? $this->action->nom : 'un événement'),
                    'notes_internes' => $this->commentaires
                ]);
                  // Mettre à jour l'invitation
            $this->update([
                'date_conversion' => now()
            ]);

            // Initialiser la première étape du pipeline prospect
            $firstStage = ProspectPipelineStage::orderBy('order')->first();
            if ($firstStage) {
                ProspectPipelineProgression::create([
                    'prospect_id' => $prospect->id,
                    'stage_id' => $firstStage->id,
                    'assigned_to' => $userId ?? $this->proprietaire_id,
                    'notes' => "Initié depuis l'invitation #" . $this->id
                ]);
            }
            
            // Enregistrer la conversion dans pipeline_conversions
            PipelineConversion::create([
                'source_type' => 'invite',
                'source_id' => $this->id,
                'target_type' => 'prospect',
                'target_id' => $prospect->id,
                'converted_by' => $userId ?? $this->proprietaire_id,
                'conversion_notes' => "Converti depuis invitation #" . $this->id . 
                                      " liée à " . ($this->action ? $this->action->nom : 'un événement')
            ]);

            return $prospect;
        });
    } catch (\Exception $e) {
        \Log::error('Erreur lors de la conversion de l\'invité en prospect: ' . $e->getMessage(), [
            'invite_id' => $this->id,
            'exception' => $e->getTraceAsString()
        ]);
        return null;
    }
}

    /**
     * Initialiser le pipeline pour cet invité
     */
    public function initializePipeline($userId = null): bool
{
    // Si le pipeline est déjà initialisé, ne rien faire
    if ($this->pipeline_type_id && $this->pipeline_stage_id) {
        return true;
    }
    
    // Sélectionner le type de pipeline par défaut
    // Vérifier d'abord si la colonne is_default existe
    if (Schema::hasColumn('invite_pipeline_types', 'is_default')) {
        $pipelineType = InvitePipelineType::where('is_default', true)->first();
    }
    
    // Si pas trouvé ou la colonne n'existe pas, prendre le premier
    if (empty($pipelineType)) {
        $pipelineType = InvitePipelineType::orderBy('id')->first();
    }
    
    if (!$pipelineType) {
        \Log::warning("Aucun type de pipeline trouvé pour l'invité #{$this->id}");
        return false;
    }
    
    try {
        return \DB::transaction(function () use ($pipelineType, $userId) {
            // Définir le type de pipeline
            $this->update(['pipeline_type_id' => $pipelineType->id]);
            
            // Obtenir la première étape
            $firstStage = $pipelineType->stages()->orderBy('order')->first();
            
            if (!$firstStage) {
                \Log::warning("Aucune étape trouvée pour le pipeline type #{$pipelineType->id}");
                return false;
            }
            
            // Créer la progression
            InvitePipelineProgression::create([
                'invite_id' => $this->id,
                'stage_id' => $firstStage->id,
                'completed' => false,
                'assigned_to' => $userId ?? $this->proprietaire_id
            ]);
            
            // Mettre à jour l'étape directe
            $this->update(['pipeline_stage_id' => $firstStage->id]);
            
            return true;
        });
    } catch (\Exception $e) {
        \Log::error("Erreur lors de l'initialisation du pipeline: " . $e->getMessage(), [
            'invite_id' => $this->id,
            'exception' => $e->getTraceAsString()
        ]);
        return false;
    }
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
        $nextStage = InvitePipelineStage::where('pipeline_type_id', $currentStage->pipeline_type_id)
                                      ->where('order', '>', $currentStage->order)
                                      ->orderBy('order')
                                      ->first();
        
        if (!$nextStage) {
            // Si c'est la dernière étape, vérifier si elle est marquée comme convertible
            if (Schema::hasColumn('invite_pipeline_stages', 'is_convertible') && $currentStage->is_convertible) {
                // Tentative de conversion automatique
                return (bool) $this->convertToProspect($userId);
            }
            return false;
        }
        
        // Créer la progression pour l'étape suivante
        InvitePipelineProgression::create([
            'invite_id' => $this->id,
            'stage_id' => $nextStage->id,
            'completed' => false,
            'assigned_to' => $userId ?? $this->proprietaire_id
        ]);
        
        // Mettre à jour l'étape directe
        $this->update(['pipeline_stage_id' => $nextStage->id]);
        
        return true;
    }

    /**
     * Vérifie si un rappel est nécessaire
     */
    public function needsReminder(): bool
    {
        return $this->statut === self::STATUT_ENVOYEE &&
            !$this->date_rappel &&
            $this->date_evenement &&
            $this->date_evenement->subDays(2)->isAfter(now());
    }

    /**
     * Obtenir le nom complet de l'invité
     */
    public function getFullNameAttribute(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }
    
    /**
     * Obtenir le nom de l'action (avec protection null)
     */
    public function getActionNameAttribute(): string
    {
        return $this->action ? $this->action->nom : 'Aucune action';
    }
    
    /**
     * Récupérer les invités qui nécessitent un suivi
     */
    public static function needingFollowUp()
    {
        return self::where('suivi_requis', true)
            ->whereIn('statut', [self::STATUT_PARTICIPEE, self::STATUT_ABSENTE])
            ->whereDoesntHave('prospect')
            ->orderBy('date_evenement', 'desc');
    }
}