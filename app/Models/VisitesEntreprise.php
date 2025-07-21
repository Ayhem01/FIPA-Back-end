<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisitesEntreprise extends Model
{
    use HasFactory;
    protected $fillable = [
        'encadre_avec_programme',
        'entreprise_importante',
        'initiateur_id',
        'nombre_visites',
        'date_contact',
        'raison_sociale',
        'responsable',
        'fonction',
        'nationalite_id',
        'secteur_id',
        'activite',
        'adresse',
        'telephone',
        'fax',
        'email',
        'site_web',
        'date_visite',
        'pr',
        'responsable_suivi_id',
        'programme_pdf',
        'services_appreciation'
    ];

    public function initiateur()
    {
        return $this->belongsTo(Initiateurs::class, 'initiateur_id');
    }
    public function nationalite()
    {
        return $this->belongsTo(Nationalite::class, 'nationalite_id');
    }
    public function secteur()
    {
        return $this->belongsTo(Secteur::class, 'secteur_id');
    }
    public function responsableSuivi()
    {
        return $this->belongsTo(responsableSuivi::class, 'responsable_suivi_id');
    }
    public function action()
    {
        return $this->belongsTo(Action::class, 'action_id');
    }
}
