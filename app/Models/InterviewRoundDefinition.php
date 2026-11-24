<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterviewRoundDefinition extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'default_duration_minutes' => 'integer',
            'is_mandatory' => 'boolean',
        ];
    }

    public function jobRequirement(): BelongsTo
    {
        return $this->belongsTo(JobRequirement::class);
    }

    public function interviewRecords(): HasMany
    {
        return $this->hasMany(InterviewRecord::class);
    }
}
