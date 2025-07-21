<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pays extends Model
{
    use HasFactory;
    protected $fillable = ['name_pays'];
    public function ctes()
    {
        return $this->hasMany(CTE::class, 'pays_id');
    }
    public function seminares_pays()
    {
        return $this->hasMany(SeminaireJIPays::class, 'pays_id');
    }
    public function seminaireJISecteur()
    {
        return $this->hasMany(SeminairesJISecteur::class, 'pays_id');
    }
    public function salons()
    {
        return $this->hasMany(Salons::class, 'pays_id');
    }
    public function salonsSectoriels()
    {
        return $this->hasMany(SalonSectoriels::class, 'pays_id');
    }
    public function demarchagesDirects()
    {
        return $this->hasMany(DemarchageDirect::class, 'pays_id');
    }
}
