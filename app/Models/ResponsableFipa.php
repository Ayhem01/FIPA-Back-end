<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResponsableFipa extends Model
{
    use HasFactory;
    protected $fillable = ['nom', 'prenom', 'email', 'telephone', 'fonction'];
    protected $table = 'responsable_fipa';
    
    public function delegations()
    {
        return $this->hasMany(Delegations::class, 'responsable_fipa_id');
    }
    public function seminaireJIPays()
    {
        return $this->hasMany(SeminaireJIPays::class, 'responsable_fipa_id');
    }
    public function seminaireJISecteur()
    {
        return $this->hasMany(SeminairesJISecteur::class, 'responsable_fipa_id');
    }
    
}
