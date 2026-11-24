<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'invitation_accepted_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'notification_preferences' => 'array',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot(['team_role', 'joined_at', 'is_active'])
            ->withTimestamps();
    }

    public function jobAssignments(): HasMany
    {
        return $this->hasMany(JobAssignment::class, 'recruiter_id');
    }

    public function assignedJobs(): BelongsToMany
    {
        return $this->belongsToMany(JobRequirement::class, 'job_assignments', 'recruiter_id', 'job_requirement_id')
            ->withPivot(['is_primary', 'daily_sourcing_target', 'submission_target', 'priority', 'status', 'deadline_date'])
            ->withTimestamps();
    }

    public function sourcedCandidates(): HasMany
    {
        return $this->hasMany(Candidate::class, 'assigned_recruiter_id');
    }

    public function candidateApplications(): HasMany
    {
        return $this->hasMany(CandidateApplication::class, 'recruiter_id');
    }

    public function callLogs(): HasMany
    {
        return $this->hasMany(CallLog::class, 'recruiter_id');
    }

    public function clientSubmissions(): HasMany
    {
        return $this->hasMany(ClientSubmission::class, 'recruiter_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function loginActivities(): HasMany
    {
        return $this->hasMany(LoginActivity::class);
    }

    public function roleSlug(): string
    {
        return $this->role?->slug ?? 'recruiter';
    }

    public function hasRole(string|array $roles): bool
    {
        $slug = $this->roleSlug();
        if (is_array($roles)) {
            return in_array($slug, $roles, true);
        }

        return $slug === $roles;
    }

    public function isSuperAdmin(): bool
    {
        return $this->roleSlug() === 'super_admin';
    }

    public function isManager(): bool
    {
        return $this->roleSlug() === 'manager';
    }

    public function isTeamLeader(): bool
    {
        return $this->roleSlug() === 'team_leader';
    }

    public function isRecruiter(): bool
    {
        return $this->roleSlug() === 'recruiter';
    }

    public function isClient(): bool
    {
        return $this->roleSlug() === 'client';
    }

    public function isInternalStaff(): bool
    {
        return ! $this->isClient();
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->role) {
            return false;
        }

        return $this->role->permissions->contains('slug', $permissionSlug);
    }
}
