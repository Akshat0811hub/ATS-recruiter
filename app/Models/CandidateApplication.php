<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CandidateApplication extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public const MAIN_STAGES = [
        'sourced' => 'Sourced',
        'contacted' => 'Contacted',
        'interested' => 'Interested',
        'screened' => 'Screened',
        'submitted' => 'Submitted',
        'client_shortlisted' => 'Client Shortlisted',
        'interview_scheduled' => 'Interview Scheduled',
        'interview_in_progress' => 'Interview in Progress',
        'selected' => 'Selected',
        'offer_released' => 'Offer Released',
        'offer_accepted' => 'Offer Accepted',
        'joining_pending' => 'Joining Pending',
        'joined' => 'Joined',
    ];

    public const ALTERNATIVE_STATES = [
        'not_interested' => 'Not Interested',
        'rejected' => 'Rejected',
        'on_hold' => 'On Hold',
        'dropped_out' => 'Dropped Out',
        'did_not_join' => 'Did Not Join',
    ];

    protected function casts(): array
    {
        return [
            'stage_updated_at' => 'datetime',
            'applied_ctc' => 'decimal:2',
            'expected_ctc' => 'decimal:2',
            'notice_period_days' => 'integer',
            'current_interview_round' => 'integer',
            'is_active' => 'boolean',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CandidateApplication $app) {
            if (empty($app->application_code)) {
                $nextId = (static::withTrashed()->max('id') ?? 0) + 1;
                $app->application_code = sprintf('APP-%s-%04d', now()->format('Y'), $nextId);
            }
            if (empty($app->stage_updated_at)) {
                $app->stage_updated_at = now();
            }
            if ($app->lock_version === null) {
                $app->lock_version = 1;
            }
        });
    }

    public static function allStages(): array
    {
        return array_merge(self::MAIN_STAGES, self::ALTERNATIVE_STATES);
    }

    public function stageLabel(): string
    {
        return self::allStages()[$this->pipeline_stage] ?? ucfirst(str_replace('_', ' ', $this->pipeline_stage));
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function jobRequirement(): BelongsTo
    {
        return $this->belongsTo(JobRequirement::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recruiter_id');
    }

    public function screeningRecords(): HasMany
    {
        return $this->hasMany(ScreeningRecord::class)->latest();
    }

    public function latestScreening(): HasOne
    {
        return $this->hasOne(ScreeningRecord::class)->latestOfMany();
    }

    public function callLogs(): HasMany
    {
        return $this->hasMany(CallLog::class)->orderByDesc('called_at');
    }

    public function clientSubmission(): HasOne
    {
        return $this->hasOne(ClientSubmission::class);
    }

    public function interviewRecords(): HasMany
    {
        return $this->hasMany(InterviewRecord::class)->orderBy('round_number');
    }

    public function offer(): HasOne
    {
        return $this->hasOne(Offer::class);
    }

    public function joiningRecord(): HasOne
    {
        return $this->hasOne(JoiningRecord::class);
    }

    public function placement(): HasOne
    {
        return $this->hasOne(Placement::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(StatusHistory::class, 'trackable_id')
            ->where('trackable_type', self::class)
            ->orderByDesc('created_at');
    }
}
