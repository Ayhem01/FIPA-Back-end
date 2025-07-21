<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Binomes extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'email', 'phone', 'poste']; 
    public function salons()
    {
        return $this->hasMany(Salons::class, 'binome_id');
    }
    public function seminares_pays()
    {
        return $this->hasMany(SeminaireJIPays::class, 'binome_id');
    }
    public function seminaireJISecteur()
    {
        return $this->hasMany(SeminairesJISecteur::class, 'binome_id');
    }
    public function salonsSectoriels()
    {
        return $this->hasMany(SalonSectoriels::class, 'binome_id');
    }
}
