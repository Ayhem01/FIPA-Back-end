<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nationalite extends Model
{
    use HasFactory;
    
    protected $fillable = ['name'];

    public function media()
    {
        return $this->hasMany(Media::class, 'nationalite_id');
    }
    public function delegations()
    {
        return $this->hasMany(Delegations::class, 'nationalite_id');
    }
    public function visitesEntreprises()
    {
        return $this->hasMany(VisitesEntreprise::class, 'nationalite_id');
    }
}
