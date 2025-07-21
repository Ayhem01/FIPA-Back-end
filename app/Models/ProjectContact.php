<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectContact extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id', 'name', 'title', 'email', 
        'phone', 'is_primary', 'is_external', 'notes'
    ];
    
    protected $casts = [
        'is_primary' => 'boolean',
        'is_external' => 'boolean'
    ];
    
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}