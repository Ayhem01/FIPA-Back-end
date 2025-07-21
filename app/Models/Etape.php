<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Etape extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'description',
        'ordre',
        'couleur',
        'duree_estimee',
        'est_obligatoire',
        'action_id',
        'type', 
    ];

    protected $casts = [
        'est_obligatoire' => 'boolean',
    ];

    /**
     * L'action associée à cette étape
     */
    public function action()
    {
        return $this->belongsTo(Action::class);
    }

    /**
     * Les invités qui en sont à cette étape
     */
    public function invites()
    {
        return $this->hasMany(Invite::class);
    }
    public function scopeForType($query, $type)
{
    return $query->where('type', $type);
}
}