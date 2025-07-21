<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResponsableBureauMedia extends Model
{
    use HasFactory;
    protected $table = 'responsables_bureau_media';


    protected $fillable = ['name'];

    public function medias()
    {
        return $this->hasMany(Media::class, 'responsable_bureau_media_id');
    }
}
