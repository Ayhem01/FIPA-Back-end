<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class CTE extends Model
{
    use HasFactory;
    protected $fillable = [
        'prenom',
        'nom',
        'adresse',
        'tel',
        'fax',
        'email',
        'age',
        'initiateur_id',
        'date_contact',
        'poste',
        'diplome',
        'ste',
        'pays_id',
        'lieu',
        'secteur_id',
        'historique_date_debut',
        'historique_ste',
        'historique_poste',
        'historique_date_fin',
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
    public function action()
    {
        return $this->belongsTo(Action::class, 'action_id');
    }
}
