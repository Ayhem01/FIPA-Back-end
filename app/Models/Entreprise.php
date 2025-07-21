<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Entreprise extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nom',
        'logo',
        'site_web',
        'telephone',
        'email',
        'adresse',
        'ville',
        'code_postal',
        'pays',
        'secteur_id',
        'taille',
        'capital',
        'chiffre_affaires',
        'date_creation',
        'description',
        'notes',
        'statut', // actif, inactif, prospect, client, etc.
        'type', // entreprise, organisme public, association, etc.
        'proprietaire_id', // utilisateur responsable
        'pipeline_stage_id',
        'pipeline_type_id',
    ];

    protected $casts = [
        'date_creation' => 'date',
        'capital' => 'float',
        'chiffre_affaires' => 'float',
    ];

    /**
     * Les invités liés à cette entreprise
     */
    public function invites()
    {
        return $this->hasMany(Invite::class);
    }

    /**
     * Les contacts liés à cette entreprise
     */
    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Le secteur d'activité de l'entreprise
     */
    public function secteur()
    {
        return $this->belongsTo(Secteur::class);
    }

    /**
     * Le propriétaire/responsable de l'entreprise
     */
    public function proprietaire()
    {
        return $this->belongsTo(User::class, 'proprietaire_id');
    }

    /**
     * L'étape de pipeline actuelle de l'entreprise
     */
    public function pipelineStage()
    {
        return $this->belongsTo(PipelineStage::class);
    }

    /**
     * Le type de pipeline de l'entreprise
     */
    public function pipelineType()
    {
        return $this->belongsTo(ProjectPipelineType::class, 'pipeline_type_id');
    }

    /**
     * Les projets liés à cette entreprise
     */
    public function projets()
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Les actions organisées par cette entreprise
     */
    public function actions()
    {
        return $this->hasMany(Action::class);
    }

    /**
     * Les tâches liées à cette entreprise
     */
    public function taches()
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Accesseur pour formater le nom en majuscules
     */
    public function getNomFormateAttribute()
    {
        return strtoupper($this->nom);
    }

    /**
     * Accesseur pour obtenir l'âge de l'entreprise en années
     */
    public function getAgeEntrepriseAttribute()
    {
        if (!$this->date_creation) {
            return null;
        }

        return Carbon::parse($this->date_creation)->diffInYears(Carbon::now());
    }

    /**
     * Accesseur pour obtenir le nombre d'invités actifs
     */
    public function getInvitesActifsCountAttribute()
    {
        return $this->invites()
            ->whereNotIn('statut', ['refusee', 'absente', 'aucune_reponse'])
            ->count();
    }

    /**
     * Scope pour les entreprises actives
     */
    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }

    /**
     * Scope pour les entreprises créées dans les X derniers jours
     */
    public function scopeRecentlyCreated($query, $days = 30)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Scope pour filtrer par secteur
     */
    public function scopeBySecteur($query, $secteurId)
    {
        return $query->where('secteur_id', $secteurId);
    }
}
