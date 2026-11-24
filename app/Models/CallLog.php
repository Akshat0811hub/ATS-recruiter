<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallLog extends Model
{
    protected $guarded = ['id'];

    public const OUTCOMES = [
        'interested' => 'Interested',
        'not_interested' => 'Not Interested',
        'not_answered' => 'Not Answered',
        'busy' => 'Busy',
        'switched_off' => 'Switched Off',
        'call_back_requested' => 'Call Back Requested',
        'incorrect_number' => 'Incorrect Number',
        'not_eligible' => 'Not Eligible',
        'screening_completed' => 'Screening Completed',
        'follow_up_required' => 'Follow-up Required',
    ];

    protected function casts(): array
    {
        return [
            'called_at' => 'datetime',
            'duration_seconds' => 'integer',
            'screening_qa' => 'array',
            'current_ctc' => 'decimal:2',
            'expected_ctc' => 'decimal:2',
            'notice_period_days' => 'integer',
            'follow_up_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function jobRequirement(): BelongsTo
    {
        return $this->belongsTo(JobRequirement::class);
    }

    public function candidateApplication(): BelongsTo
    {
        return $this->belongsTo(CandidateApplication::class);
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recruiter_id');
    }
}
