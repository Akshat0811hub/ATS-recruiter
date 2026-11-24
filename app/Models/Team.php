<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'daily_sourcing_target' => 'integer',
            'daily_call_target' => 'integer',
            'daily_submission_target' => 'integer',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot(['team_role', 'joined_at', 'is_active'])
            ->withTimestamps();
    }

    public function directUsers(): HasMany
    {
        return $this->hasMany(User::class, 'team_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'assigned_team_id');
    }

    public function jobRequirements(): HasMany
    {
        return $this->hasMany(JobRequirement::class, 'assigned_team_id');
    }
}
