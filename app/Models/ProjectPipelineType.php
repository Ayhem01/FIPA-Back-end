<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectPipelineType extends Model
{
    use HasFactory;
    
    protected $fillable = ['name', 'slug', 'description', 'order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function stages()
    {
        return $this->hasMany(PipelineStage::class, 'pipeline_type_id')
            ->orderBy('order');
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'pipeline_type_id');
    }
}