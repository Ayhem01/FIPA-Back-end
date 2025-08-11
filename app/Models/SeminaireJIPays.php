<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeminaireJIPays extends Model
{
    use HasFactory;
    protected $fillable = [
        'proposee',
        'programmee',
        'non_programmee',
        'validee',
        'realisee',
        'reportee',
        'annulee',
        'motif',
        'responsable_fipa_id',
        'inclure',
        'intitule',
        'theme',
        'date_debut',
        'date_fin',
        'pays_id',
        'region',
        'action_conjointe',
        'binome_id',
        'proposee_par',
        'objectifs',
        'lieu',
        'type_participation',
        'details_participation_active',
        'type_organisation',
        'partenaires_tunisiens',
        'partenaires_etrangers',
        'officiels',
        'presence_dg',
        'programme_deroulement',
        'diaspora',
        'diaspora_details',
        'location_salle',
        'media_communication',
        'besoin_binome',
        'autre_organisme',
        'outils_promotionnels',
        'date_butoir',
        'budget_prevu',
        'budget_realise',
        'nb_entreprises',
        'nb_multiplicateurs',
        'nb_institutionnels',
        'nb_articles_presse',
        'fichier_pdf',
        'evalutation_recommandation',
        'action_id'
    ];
    public static function createFromAction($action, $request)
    {
        $data = $request->only((new self)->getFillable());
        $data['action_id'] = $action->id;
        return self::create($data);
    }
    public function responsable_fipa()
    {
        return $this->belongsTo(ResponsableFipa::class, 'responsable_fipa_id');
    }
    public function pays()
    {
        return $this->belongsTo(Pays::class, 'pays_id');
    }
    public function binomes()
    {
        return $this->belongsTo(Binomes::class, 'binome_id');
    }
    public function action()
    {
        return $this->belongsTo(Action::class, 'action_id');
    }
}
