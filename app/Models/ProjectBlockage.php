<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectBlockage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id', 'name', 'type', 'description', 
        'status', 'priority', 'assigned_to', 
        'follow_up_date', 'blocks_progress'
    ];
    
    protected $casts = [
        'follow_up_date' => 'date',
        'blocks_progress' => 'boolean'
    ];
    
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
    
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}