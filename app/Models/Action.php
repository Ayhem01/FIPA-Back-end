<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Action extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nom',
        'description',
        'type',
        'date_debut',
        'date_fin',
        'lieu',    
        'ville',      
        'pays',     
        'statut',
        'responsable_id',
        'notes_internes'
    ];

    protected $casts = [
    
    ];

    /**
     * Les invités associés à cette action
     */
    public function invites()
    {
        return $this->hasMany(Invite::class);
    }

    public function etapes()
{
    return $this->hasMany(Etape::class)->orderBy('ordre');
}

    /**
     * L'entreprise associée à cette action
     */
    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    /**
     * Le responsable de l'action
     */
    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }


    /**
     * Obtenir le nombre d'invités confirmés
     */
    public function getInvitesConfirmesCountAttribute()
    {
        return $this->invites()->whereIn('statut', ['confirmee', 'participation_confirmee'])->count();
    }

    /**
     * Scope pour les actions à venir
     */
    public function scopeAVenir($query)
    {
        return $query->where('date_debut', '>=', now())
                     ->whereNotIn('statut', ['terminee', 'annulee']);
    }

    /**
     * Scope pour les actions passées
     */
    public function scopePassees($query)
    {
        return $query->where('date_debut', '<', now())
                     ->orWhere('statut', 'terminee');
    }
}