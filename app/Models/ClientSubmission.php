<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientSubmission extends Model
{
    protected $guarded = ['id'];

    public const DECISIONS = [
        'pending_review' => 'Pending Review',
        'shortlisted' => 'Shortlisted',
        'rejected' => 'Rejected',
        'on_hold' => 'On Hold',
        'additional_info_required' => 'Additional Information Required',
        'interview_requested' => 'Interview Requested',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_relevant_experience' => 'decimal:1',
            'snapshot_total_experience' => 'decimal:1',
            'snapshot_current_ctc' => 'decimal:2',
            'snapshot_expected_ctc' => 'decimal:2',
            'snapshot_notice_period_days' => 'integer',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ClientSubmission $sub) {
            if (empty($sub->submission_code)) {
                $nextId = (static::max('id') ?? 0) + 1;
                $sub->submission_code = sprintf('SUB-%s-%04d', now()->format('Y'), $nextId);
            }
            if (empty($sub->submitted_at)) {
                $sub->submitted_at = now();
            }
        });
    }

    public function candidateApplication(): BelongsTo
    {
        return $this->belongsTo(CandidateApplication::class);
    }

    public function jobRequirement(): BelongsTo
    {
        return $this->belongsTo(JobRequirement::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recruiter_id');
    }

    public function resumeVersion(): BelongsTo
    {
        return $this->belongsTo(ResumeVersion::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function feedbackHistory(): HasMany
    {
        return $this->hasMany(ClientFeedback::class)->latest();
    }
}
