<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Initiateurs extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'email'];
    

public function ctes()
    {
        return $this->hasMany(CTE::class, 'initiateur_id');
    }
    public function salons()
    {
        return $this->hasMany(Salons::class, 'initiateur_id');
    }
    public function seminares_pays()
    {
        return $this->hasMany(SeminaireJIPays::class, 'initiateur_id');
    }
    public function delegations()
    {
        return $this->hasMany(Delegations::class, 'initiateur_id');
    }
    public function visitesEntreprises()
    {
        return $this->hasMany(VisitesEntreprise::class, 'initiateur_id');
    }
    public function salonsSectoriels()
    {
        return $this->hasMany(SalonSectoriels::class, 'initiateur_id');
    }
    public function demarchagesDirects()
    {
        return $this->hasMany(DemarchageDirect::class, 'initiateur_id');
    }
}