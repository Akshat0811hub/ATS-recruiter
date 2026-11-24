<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreeningRecord extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'education_match' => 'boolean',
            'experience_match' => 'boolean',
            'skills_match' => 'boolean',
            'salary_compatible' => 'boolean',
            'location_compatible' => 'boolean',
            'notice_period_compatible' => 'boolean',
            'communication_rating' => 'integer',
            'question_answers' => 'array',
            'screened_at' => 'datetime',
        ];
    }

    public function candidateApplication(): BelongsTo
    {
        return $this->belongsTo(CandidateApplication::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function jobRequirement(): BelongsTo
    {
        return $this->belongsTo(JobRequirement::class);
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recruiter_id');
    }
}
