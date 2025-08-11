<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeminairesJISecteur extends Model
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
        'inclure',
        'responsable_fipa_id',
        'intitule',
        'theme',
        'date_debut',
        'date_fin',
        'pays_id',
        'region',
        'secteur_id',
        'groupe_id',
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
        'avec_diaspora',
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
        'fichier_presence',
        'evaluation_recommandations',
        'contacts_realises',
        'action_id'
    ];
    public static function createFromAction($action, $request)
    {
        $data = $request->only((new self)->getFillable());
        $data['action_id'] = $action->id;
        return self::create($data);
    }

    public function responbsablefipa()
    {
        return $this->belongsTo(ResponsableFipa::class, 'responsable_fipa_id');
    }

    public function pays()
    {
        return $this->belongsTo(Pays::class, 'pays_id');
    }
    public function secteur()
    {
        return $this->belongsTo(Secteur::class, 'secteur_id');
    }
    public function groupe()
    {
        return $this->belongsTo(Groupe::class, 'groupe_id');
    }
    public function binome()
    {
        return $this->belongsTo(Binomes::class, 'binome_id');
    }
    public function action()
    {
        return $this->belongsTo(Action::class, 'action_id');
    }
}
