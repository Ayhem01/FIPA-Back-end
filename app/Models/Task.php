<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'start',
        'end',
        'all_day',
        'type',
        'status',
        'priority',
        'color',
        'user_id',
        'assignee_id',
        'reminder_24h_sent',
        'reminder_10min_sent',
        
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'all_day' => 'boolean',
        'reminder_24h_sent' => 'boolean',
        'reminder_10min_sent' => 'boolean',
    ];

    /**
     * L'utilisateur qui a créé la tâche
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * L'utilisateur assigné à cette tâche
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

   
   
}