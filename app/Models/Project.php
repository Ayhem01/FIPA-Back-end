<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title', 'description', 'company_name', 
        'idea', 'in_progress', 'in_production',
        'secteur_id', 'governorate_id', 'responsable_id',
        'market_target', 'nationality', 'foreign_percentage',
        'investment_amount', 'jobs_expected', 'industrial_zone',
        'pipeline_type_id', 'pipeline_stage_id', 'is_blocked', 
        'start_date', 'end_date',
        'contact_source', 'initial_contact_person', 'first_contact_date'
    ];

    protected $casts = [
        'idea' => 'boolean',
        'in_progress' => 'boolean',
        'in_production' => 'boolean',
        'is_blocked' => 'boolean',
        'foreign_percentage' => 'decimal:2',
        'investment_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'first_contact_date' => 'date',
    ];

    // Relations
    public function secteur()
    {
        return $this->belongsTo(Secteur::class);
    }

    // Décommentez cette méthode si la table governorates existe
    // public function governorate()
    // {
    //     return $this->belongsTo(Governorate::class);
    // }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function pipelineType()
    {
        return $this->belongsTo(ProjectPipelineType::class, 'pipeline_type_id');
    }

    public function pipelineStage()
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    public function followUps()
    {
        return $this->hasMany(ProjectFollowUp::class);
    }

    public function blockages()
    {
        return $this->hasMany(ProjectBlockage::class);
    }

    public function contacts()
    {
        return $this->hasMany(ProjectContact::class);
    }

    public function documents()
    {
        return $this->hasMany(ProjectDocument::class);
    }
        
    
    // Accesseur pour le dernier contact
    public function getLastContactAttribute()
    {
        $lastFollowUp = $this->followUps()->latest('follow_up_date')->first();
        return $lastFollowUp ? $lastFollowUp->follow_up_date : null;
    }
}