<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalonSectoriels extends Model
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
        'initiateur_id',
        'intitule',
        'numero_edition',
        'site_web',
        'organisateur',
        'convention_affaire',
        'date_debut',
        'date_fin',
        'pays_id',
        'region',
        'theme',
        'secteur_id',
        'groupe_id',
        'categorie',
        'presence_conjointe',
        'binome_id',
        'contacts_initiateur',
        'contacts_binome',
        'contacts_total',
        'contacts_interessants_initiateur',
        'contacts_interessants_binome',
        'objectif_contacts',
        'objectif_veille_concurrentielle',
        'objectif_veille_technologique',
        'objectif_relation_relais',
        'historique_edition',
        'stand',
        'media',
        'besoin_binome',
        'autre_organisme',
        'outils_promotionnels',
        'date_butoir',
        'budget_prevu',
        'budget_realise',
        'resultat_veille_concurrentielle',
        'resultat_veille_technologique',
        'relation_institutions',
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
    public function initiateur()
    {
        return $this->belongsTo(Initiateurs::class, 'initiateur_id');
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
