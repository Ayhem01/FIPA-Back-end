<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invite extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'entreprise_id',
        'action_id',
        'etape_id',
        'nom',
        'prenom',
        'email',
        'telephone',
        'fonction',
        'type_invite', // 'interne', 'externe'
        'statut', // 'en_attente', 'envoyee', 'confirmee', 'refusee', 'participee', 'absente'
        'suivi_requis',
        'date_invitation',
        'date_evenement',
        'commentaires',
        'proprietaire_id'
    ];

    protected $casts = [
        'suivi_requis' => 'boolean',
        'date_invitation' => 'datetime',
        'date_evenement' => 'datetime',
    ];

    public function entreprise()
    {
        return $this->belongsTo(Entreprise::class);
    }

    public function action()
    {
        return $this->belongsTo(Action::class);
    }

    public function etape()
    {
        return $this->belongsTo(Etape::class);
    }

    public function proprietaire()
    {
        return $this->belongsTo(User::class, 'proprietaire_id');
    }
}