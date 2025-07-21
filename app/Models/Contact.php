<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'entreprise_id',
        'nom',
        'prenom',
        'fonction',
        'email',
        'telephone_fixe',
        'telephone_mobile',
        'adresse',
        'ville',
        'code_postal',
        'pays',
        'est_principal',
        'linkedin',
        'notes',
        'statut', // actif, inactif, ancien, etc.
        'date_naissance',
        'proprietaire_id'
    ];

    protected $casts = [
        'est_principal' => 'boolean',
        'date_naissance' => 'date',
    ];

    /**
     * L'entreprise associée à ce contact
     */
    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    /**
     * Le propriétaire/responsable de ce contact
     */
    public function proprietaire()
    {
        return $this->belongsTo(User::class, 'proprietaire_id');
    }

    /**
     * Les invitations envoyées à ce contact
     */
    public function invitations()
    {
        return $this->hasMany(Invite::class, 'email', 'email');
    }

    /**
     * Nom complet du contact
     */
    public function getNomCompletAttribute()
    {
        return $this->prenom . ' ' . $this->nom;
    }

    /**
     * Scope pour les contacts principaux
     */
    public function scopePrincipal($query)
    {
        return $query->where('est_principal', true);
    }

    /**
     * Scope pour les contacts actifs
     */
    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }
}