<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'locations' => 'array',
            'placement_fee_percentage' => 'decimal:2',
            'flat_placement_fee' => 'decimal:2',
            'replacement_period_days' => 'integer',
            'payment_terms_days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            if (empty($client->client_code)) {
                $nextId = (static::withTrashed()->max('id') ?? 0) + 1;
                $client->client_code = sprintf('CLT-%s-%04d', now()->format('Y'), $nextId);
            }
        });
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function assignedTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'assigned_team_id');
    }

    public function agreementStorage(): BelongsTo
    {
        return $this->belongsTo(StorageRecord::class, 'agreement_storage_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function portalUsers(): HasMany
    {
        return $this->hasMany(User::class, 'client_id');
    }

    public function jobRequirements(): HasMany
    {
        return $this->hasMany(JobRequirement::class);
    }

    public function candidateApplications(): HasMany
    {
        return $this->hasMany(CandidateApplication::class);
    }

    public function clientSubmissions(): HasMany
    {
        return $this->hasMany(ClientSubmission::class);
    }

    public function interviewRecords(): HasMany
    {
        return $this->hasMany(InterviewRecord::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function joiningRecords(): HasMany
    {
        return $this->hasMany(JoiningRecord::class);
    }

    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    public function communications(): HasMany
    {
        return $this->hasMany(CommunicationRecord::class);
    }
}
