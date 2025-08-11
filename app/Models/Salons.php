<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salons extends Model
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
        'date_debut',
        'date_fin',
        'pays_id',
        'region',
        'theme',
        'categorie',
        'presence_conjointe',
        'binome_id',
        'contacts_initiateur',
        'contacts_binome',
        'contacts_total',
        'objectif_contacts',
        'objectif_veille_concurrentielle',
        'objectif_veille_technologique',
        'objectif_relation_relais',
        'historique_edition',
        'besoin_stand',
        'besoin_media',
        'besoin_binome',
        'besoin_autre_organisme',
        'outils_promotionnels',
        'date_butoir',
        'budget_prevu',
        'budget_realise',
        'resultat_veille_concurrentielle',
        'resultat_veille_technologique',
        'resultat_relation_institutions',
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
    public function binomes()
    {
        return $this->belongsTo(Binomes::class, 'binome_id');
    }
    public function initiateur()
    {
        return $this->belongsTo(Initiateurs::class, 'initiateur_id');
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
