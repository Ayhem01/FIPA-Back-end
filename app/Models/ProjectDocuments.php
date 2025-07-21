<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id', 'name', 'file_path', 'file_type',
        'file_size', 'uploaded_by', 'description'
    ];
    
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
    
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}