<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VavSiegeMedia extends Model
{
    use HasFactory;
    protected $table = 'vav_sieges_media';


    protected $fillable = ['name'];

    public function medias()
    {
        return $this->hasMany(Media::class, 'vav_siege_media_id');
    }
}
