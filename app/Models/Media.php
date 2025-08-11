<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use HasFactory;
    protected $fillable = [
        'proposee',
        'programmee',
        'realisee',
        'reportee',
        'annulee',
        'action',
        'proposee_par',
        'responsable_bureau_media_id',
        'vav_siege_media_id',
        'type_action',
        'duree',
        'zone_impact',
        'cible',
        'objectif',
        'resultats_attendus',
        'budget',
        'devise',
        'imputation_financiere',
        'date_debut',
        'date_fin',
        'type_media',
        'nationalite_id',
        'langue',
        'diffusion',
        'tirage_audience',
        'composition_lectorat',
        'collaboration_fipa',
        'volume_couverture',
        'regie_publicitaire',
        'media_contact',
        'evaluation',
        'reconduction',
        'commentaires_specifiques',
        'action_id'
    ];

    public static function createFromAction($action, $request)
{
    $data = $request->only((new self)->getFillable());
    $data['action_id'] = $action->id;
    return self::create($data);
}

    public function responsableBureau()
    {
        return $this->belongsTo(ResponsableBureauMedia::class, 'responsable_bureau_media_id');
    }

    public function vavSiege()
    {
        return $this->belongsTo(VavSiegeMedia::class, 'vav_siege_media_id');
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
