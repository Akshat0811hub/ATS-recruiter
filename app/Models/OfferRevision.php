<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferRevision extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'offered_salary' => 'decimal:2',
            'expected_joining_date' => 'date',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function reviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revised_by');
    }
}
