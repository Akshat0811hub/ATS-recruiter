<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplacementRecord extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'exit_date' => 'date',
            'within_replacement_period' => 'boolean',
            'vacancy_reopened' => 'boolean',
        ];
    }

    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class);
    }

    public function joiningRecord(): BelongsTo
    {
        return $this->belongsTo(JoiningRecord::class);
    }

    public function jobRequirement(): BelongsTo
    {
        return $this->belongsTo(JobRequirement::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function originalCandidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'original_candidate_id');
    }

    public function replacementCandidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'replacement_candidate_id');
    }

    public function authoriser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorised_by');
    }
}
