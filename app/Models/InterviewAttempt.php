<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewAttempt extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'scheduled_at' => 'datetime',
        ];
    }

    public function interviewRecord(): BelongsTo
    {
        return $this->belongsTo(InterviewRecord::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
