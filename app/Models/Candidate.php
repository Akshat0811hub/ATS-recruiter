<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'total_experience' => 'decimal:1',
            'relevant_experience' => 'decimal:1',
            'current_ctc' => 'decimal:2',
            'expected_ctc' => 'decimal:2',
            'notice_period_days' => 'integer',
            'is_serving_notice' => 'boolean',
            'last_working_date' => 'date',
            'tags' => 'array',
            'consent_recorded_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'custom_fields_data' => 'array',
        ];
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) > 10 && str_starts_with($digits, '91')) {
            $digits = substr($digits, -10);
        } elseif (strlen($digits) > 10 && str_starts_with($digits, '0')) {
            $digits = ltrim($digits, '0');
        }

        return $digits ?: null;
    }

    public static function normalizeEmail(?string $email): ?string
    {
        if (! $email) {
            return null;
        }

        return strtolower(trim($email)) ?: null;
    }

    protected static function booted(): void
    {
        static::creating(function (Candidate $candidate) {
            if (empty($candidate->candidate_code)) {
                $nextId = (static::withTrashed()->max('id') ?? 0) + 1;
                $candidate->candidate_code = sprintf('CND-%s-%04d', now()->format('Y'), $nextId);
            }
            $candidate->normalized_mobile = static::normalizePhone($candidate->mobile_number) ?? $candidate->mobile_number;
            $candidate->normalized_alternate = static::normalizePhone($candidate->alternate_number);
            $candidate->normalized_email = static::normalizeEmail($candidate->email);
            if (empty($candidate->consent_recorded_at)) {
                $candidate->consent_recorded_at = now();
            }
        });

        static::updating(function (Candidate $candidate) {
            $candidate->normalized_mobile = static::normalizePhone($candidate->mobile_number) ?? $candidate->mobile_number;
            $candidate->normalized_alternate = static::normalizePhone($candidate->alternate_number);
            $candidate->normalized_email = static::normalizeEmail($candidate->email);
        });
    }

    public function assignedRecruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_recruiter_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CandidateContact::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(CandidateSkill::class);
    }

    public function resumeVersions(): HasMany
    {
        return $this->hasMany(ResumeVersion::class)->orderByDesc('version_number');
    }

    public function primaryResume(): HasOne
    {
        return $this->hasOne(ResumeVersion::class)->where('is_primary', true)->latestOfMany('version_number');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CandidateDocument::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CandidateNote::class)->latest();
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CandidateApplication::class)->latest();
    }

    public function callLogs(): HasMany
    {
        return $this->hasMany(CallLog::class)->orderByDesc('called_at');
    }

    public function clientSubmissions(): HasMany
    {
        return $this->hasMany(ClientSubmission::class)->latest();
    }

    public function interviewRecords(): HasMany
    {
        return $this->hasMany(InterviewRecord::class)->orderBy('round_number');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class)->latest();
    }

    public function joiningRecords(): HasMany
    {
        return $this->hasMany(JoiningRecord::class)->latest();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('due_date');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'subject_id')
            ->where('subject_type', self::class)
            ->orderByDesc('created_at');
    }
}
