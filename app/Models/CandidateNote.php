<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateNote extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_client_visible' => 'boolean',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
