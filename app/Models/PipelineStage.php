<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PipelineStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'pipeline_type_id', 'name', 'slug', 'status', 
        'color', 'order', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function pipelineType()
    {
        return $this->belongsTo(ProjectPipelineType::class, 'pipeline_type_id');
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'pipeline_stage_id');
    }
}