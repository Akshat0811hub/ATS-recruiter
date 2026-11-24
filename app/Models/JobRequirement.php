<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobRequirement extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'openings_count' => 'integer',
            'joined_count' => 'integer',
            'remaining_count' => 'integer',
            'required_skills' => 'array',
            'preferred_skills' => 'array',
            'min_experience' => 'decimal:1',
            'max_experience' => 'decimal:1',
            'min_salary' => 'decimal:2',
            'max_salary' => 'decimal:2',
            'max_notice_days' => 'integer',
            'expected_closure_date' => 'date',
            'interview_rounds_count' => 'integer',
            'custom_fields_data' => 'array',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (JobRequirement $job) {
            if (empty($job->job_code)) {
                $nextId = (static::withTrashed()->max('id') ?? 0) + 1;
                $job->job_code = sprintf('JOB-%s-%04d', now()->format('Y'), $nextId);
            }
            $job->remaining_count = max(0, (int) $job->openings_count - (int) ($job->joined_count ?? 0));
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function jdStorage(): BelongsTo
    {
        return $this->belongsTo(StorageRecord::class, 'jd_storage_id');
    }

    public function assignedTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'assigned_team_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(JobAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(JobAssignment::class)->where('status', 'active');
    }

    public function recruiters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'job_assignments', 'job_requirement_id', 'recruiter_id')
            ->withPivot(['is_primary', 'daily_sourcing_target', 'submission_target', 'priority', 'status', 'deadline_date', 'internal_instructions'])
            ->withTimestamps();
    }

    public function screeningQuestions(): HasMany
    {
        return $this->hasMany(JobScreeningQuestion::class)->orderBy('sort_order');
    }

    public function interviewRoundDefinitions(): HasMany
    {
        return $this->hasMany(InterviewRoundDefinition::class)->orderBy('round_number');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CandidateApplication::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ClientSubmission::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(InterviewRecord::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function joinings(): HasMany
    {
        return $this->hasMany(JoiningRecord::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    public function recalculateVacancies(): void
    {
        $joinedCount = $this->joinings()
            ->whereIn('status', ['joined', 'placement_completed'])
            ->count();

        $remaining = max(0, $this->openings_count - $joinedCount);

        $newStatus = $this->status;
        if ($remaining === 0 && $this->openings_count > 0) {
            $newStatus = 'successfully_closed';
        } elseif ($joinedCount > 0 && $remaining > 0) {
            $newStatus = 'partially_filled';
        } elseif ($newStatus === 'successfully_closed' && $remaining > 0) {
            $newStatus = 'sourcing_in_progress';
        }

        $this->update([
            'joined_count' => $joinedCount,
            'remaining_count' => $remaining,
            'status' => $newStatus,
            'closed_at' => $remaining === 0 ? ($this->closed_at ?? now()) : null,
        ]);
    }
}
