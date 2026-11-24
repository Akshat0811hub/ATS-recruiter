<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterviewRecord extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = [
        'not_started' => 'Not Started',
        'pending' => 'Pending',
        'scheduled' => 'Scheduled',
        'completed' => 'Completed',
        'passed' => 'Passed',
        'failed' => 'Failed',
        'on_hold' => 'On Hold',
        'rescheduled' => 'Rescheduled',
        'no_show' => 'No Show',
        'cancelled' => 'Cancelled',
        'skipped_with_authorisation' => 'Skipped with Authorisation',
    ];

    public const MODES = [
        'telephonic' => 'Telephonic',
        'video' => 'Video',
        'face_to_face' => 'Face-to-face',
        'technical_assessment' => 'Technical Assessment',
        'online_assessment' => 'Online Assessment',
    ];

    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'interview_date' => 'date',
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'candidate_confirmed' => 'boolean',
            'client_confirmed' => 'boolean',
            'score' => 'decimal:2',
            'attempts_count' => 'integer',
            'is_overridden' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (InterviewRecord $record) {
            if (empty($record->interview_code)) {
                $nextId = (static::max('id') ?? 0) + 1;
                $record->interview_code = sprintf('INT-%s-%04d', now()->format('Y'), $nextId);
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

    public function roundDefinition(): BelongsTo
    {
        return $this->belongsTo(InterviewRoundDefinition::class, 'interview_round_definition_id');
    }

    public function overrideAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by');
    }

    public function scheduler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(InterviewAttempt::class)->orderBy('attempt_number');
    }

    public function feedbackEntries(): HasMany
    {
        return $this->hasMany(InterviewFeedback::class)->latest();
    }
}
