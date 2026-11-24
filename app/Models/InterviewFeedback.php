<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewFeedback extends Model
{
    protected $table = 'interview_feedback';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'technical_score' => 'decimal:1',
            'communication_score' => 'decimal:1',
            'role_fit_score' => 'decimal:1',
            'overall_score' => 'decimal:1',
        ];
    }

    public function interviewRecord(): BelongsTo
    {
        return $this->belongsTo(InterviewRecord::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
