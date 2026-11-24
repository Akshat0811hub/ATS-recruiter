<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Placement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'joined_date' => 'date',
            'final_ctc' => 'decimal:2',
            'billable_fee' => 'decimal:2',
            'warranty_end_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Placement $placement) {
            if (empty($placement->placement_code)) {
                $nextId = (static::max('id') ?? 0) + 1;
                $placement->placement_code = sprintf('PLC-%s-%04d', now()->format('Y'), $nextId);
            }
        });
    }

    public function joiningRecord(): BelongsTo
    {
        return $this->belongsTo(JoiningRecord::class);
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

    public function replacements(): HasMany
    {
        return $this->hasMany(ReplacementRecord::class);
    }
}
