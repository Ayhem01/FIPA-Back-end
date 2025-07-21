<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResponsableSuivi extends Model
{
    use HasFactory;
    protected $table = 'responsable_suivi';
    protected $fillable = ['nom', 'prenom', 'email', 'telephone', 'fonction'];



    public function visitesEntreprises()
    {
        return $this->hasMany(VisitesEntreprise::class, 'responsable_bureau_media_id');
    }
}
