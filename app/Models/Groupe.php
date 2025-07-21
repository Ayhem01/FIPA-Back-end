<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Groupe extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description']; 

    public function delegations()
    {
        return $this->hasMany(Delegations::class, 'groupe_id');
    }
    public function seminaireJISecteur()
    {
        return $this->hasMany(SeminairesJISecteur::class, 'groupe_id');
    }
    public function salonsSectoriels()
    {
        return $this->hasMany(SalonSectoriels::class, 'groupe_id');
    }
}