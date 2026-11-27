<?php

namespace App\Models;

use App\Services\CodeGeneratorService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JoiningRecord extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = [
        'joining_pending' => 'Joining Pending',
        'joined' => 'Joined',
        'joining_delayed' => 'Joining Delayed',
        'did_not_join' => 'Did Not Join',
        'left_after_joining' => 'Left After Joining',
        'replacement_required' => 'Replacement Required',
        'placement_completed' => 'Placement Completed',
    ];

    protected function casts(): array
    {
        return [
            'expected_joining_date' => 'date',
            'actual_joining_date' => 'date',
            'candidate_confirmed' => 'boolean',
            'client_confirmed' => 'boolean',
            'final_salary' => 'decimal:2',
            'placement_value' => 'decimal:2',
            'replacement_period_days' => 'integer',
            'replacement_expiry_date' => 'date',
            'next_checkin_date' => 'date',
            'post_joining_checkins' => 'array',
            'vacancy_counted' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (JoiningRecord $joining) {
            if (empty($joining->joining_code)) {
                $joining->joining_code = CodeGeneratorService::next(
                    static::class,
                    'joining_code',
                    sprintf('JON-%s-', now()->format('Y')),
                    4
                );
            }
        });
    }

    public function candidateApplication(): BelongsTo
    {
        return $this->belongsTo(CandidateApplication::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
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

    public function joiningDocumentStorage(): BelongsTo
    {
        return $this->belongsTo(StorageRecord::class, 'joining_document_storage_id');
    }

    public function placement(): HasOne
    {
        return $this->hasOne(Placement::class);
    }

    public function replacementRecords(): HasMany
    {
        return $this->hasMany(ReplacementRecord::class);
    }
}
