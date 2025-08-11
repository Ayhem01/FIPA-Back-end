<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemarchageDirect extends Model
{
    use HasFactory;
    protected $fillable = [
        'proposee',
        'programmee',
        'realisee',
        'reportee',
        'annulee',
        'presentation',
        'initiateur_id',
        'inclure',
        'secteur_id',
        'groupe_secteur',
        'pays_id',
        'regions',
        'date_debut',
        'date_fin',
        'conjointe',
        'cadre_siege',
        'contacts_interessants_initiateur',
        'contacts_interessants_binome',
        'besoins_logistiques',
        'frais_deplacement',
        'frais_mission',
        'date_butoir',
        'budget_prevu',
        'budget_realise',
        'date_premier_mailing',
        'nb_entreprises_ciblees',
        'source_ciblage',
        'dates_relances',
        'contacts_telephoniques',
        'lettre_argumentaire',
        'nb_reponses_positives',
        'resultat_action',
        'evaluation_action',
        'action_id'
    ];

    public static function createFromAction($action, $request)
    {
        $data = $request->only((new self)->getFillable());
        $data['action_id'] = $action->id;
        return self::create($data);
    }

    public function initiateur()
    {
        return $this->belongsTo(Initiateurs::class, 'initiateur_id');
    }
    public function secteur()
    {
        return $this->belongsTo(Secteur::class, 'secteur_id');
    }
    public function pays()
    {
        return $this->belongsTo(Pays::class, 'pays_id');
    }
    public function action()
    {
        return $this->belongsTo(Action::class, 'action_id');
    }
}
