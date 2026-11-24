<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Offer extends Model
{
    protected $guarded = ['id'];

    public const STATUSES = [
        'selected' => 'Selected',
        'offer_pending' => 'Offer Pending',
        'offer_released' => 'Offer Released',
        'under_negotiation' => 'Under Negotiation',
        'offer_accepted' => 'Offer Accepted',
        'offer_rejected' => 'Offer Rejected',
        'candidate_backed_out' => 'Candidate Backed Out',
        'offer_withdrawn' => 'Offer Withdrawn',
    ];

    protected function casts(): array
    {
        return [
            'selection_date' => 'date',
            'offered_salary' => 'decimal:2',
            'negotiated_salary' => 'decimal:2',
            'offer_release_date' => 'date',
            'expected_joining_date' => 'date',
            'candidate_acceptance_date' => 'date',
            'next_followup_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Offer $offer) {
            if (empty($offer->offer_code)) {
                $nextId = (static::max('id') ?? 0) + 1;
                $offer->offer_code = sprintf('OFR-%s-%04d', now()->format('Y'), $nextId);
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

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recruiter_id');
    }

    public function offerLetterStorage(): BelongsTo
    {
        return $this->belongsTo(StorageRecord::class, 'offer_letter_storage_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(OfferRevision::class)->orderByDesc('revision_number');
    }

    public function joiningRecord(): HasOne
    {
        return $this->hasOne(JoiningRecord::class);
    }
}
