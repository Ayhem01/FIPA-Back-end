<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PipelineConversion extends Model
{
    protected $fillable = [
        'source_type',
        'source_id',
        'target_type',
        'target_id',
        'converted_by',
        'conversion_notes'
    ];

    /**
     * L'utilisateur qui a effectué la conversion
     */
    public function converter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    /**
     * L'entité source qui a été convertie
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * L'entité cible après conversion
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}