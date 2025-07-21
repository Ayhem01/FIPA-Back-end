<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectFollowUp extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id', 'user_id', 'follow_up_date', 
        'description', 'next_follow_up_date', 'completed'
    ];
    
    protected $casts = [
        'follow_up_date' => 'date',
        'next_follow_up_date' => 'date',
        'completed' => 'boolean'
    ];
    
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}