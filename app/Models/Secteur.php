<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Secteur extends Model
{
    use HasFactory;
    protected $fillable = ['name'];
    
    public function project()
    {
        return $this->hasMany(Project::class, 'secteur_id');
    }

    public function ctes()
    {
        return $this->hasMany(CTE::class, 'secteur_id');
    }
    public function delegations()
    {
        return $this->hasMany(Delegations::class, 'secteur_id');
    }
    public function visitesEntreprises()
    {
        return $this->hasMany(VisitesEntreprise::class, 'secteur_id');
    }
    public function seminaireJISecteur()
    {
        return $this->hasMany(SeminairesJISecteur::class, 'secteur_id');
    }
    public function salonsSectoriels()
    {
        return $this->hasMany(SalonSectoriels::class, 'secteur_id');
    }
    public function demarchagesDirects()
    {
        return $this->hasMany(DemarchageDirect::class, 'secteur_id');
    }
    

}
