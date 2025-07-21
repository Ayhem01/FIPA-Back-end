<?php

namespace App\Models;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delegations extends Model
{
    use HasFactory;
    protected $fillable = [
        'responsable_fipa_id',
        'date_visite',
        'delegation',
        'initiateur_id',
        'contact',
        'fonction',
        'adresse',
        'telephone',
        'fax',
        'nationalite_id',
        'email_site',
        'activite',
        'secteur_id',
        'groupe_id',
        'programme_visite',
        'evaluation_suivi',
        'liste_membres_pdf'
    ];

    public function responsableFipa()
    {
        return $this->belongsTo(ResponsableFipa::class, 'responsable_fipa_id');
    }
    public function groupe()
    {
        return $this->belongsTo(Groupe::class, 'groupe_id');
    }
    public function initiateur()
    {
        return $this->belongsTo(ResponsableFipa::class, 'initiateur_id');
    }
    public function secteur()
    {
        return $this->belongsTo(Secteur::class, 'secteur_id');
    }
    public function nationalite()
    {
        return $this->belongsTo(Nationalite::class, 'nationalite_id');
    }
    public function action()
    {
        return $this->belongsTo(Action::class, 'action_id');
    }
}
