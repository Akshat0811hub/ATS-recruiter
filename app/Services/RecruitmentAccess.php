<?php

namespace App\Services;

use App\Models\CallLog;
use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Client;
use App\Models\ClientSubmission;
use App\Models\InterviewRecord;
use App\Models\JobRequirement;
use App\Models\JoiningRecord;
use App\Models\Offer;
use App\Models\Placement;
use App\Models\ReplacementRecord;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;

class RecruitmentAccess
{
    public const MODELS = [Candidate::class, CandidateApplication::class, Client::class, JobRequirement::class, ClientSubmission::class, InterviewRecord::class, Offer::class, JoiningRecord::class, Placement::class, ReplacementRecord::class, Task::class, CallLog::class, Team::class, User::class];

    public static function query(string $model): Builder
    {
        /** @var Builder $scoped */
        $scoped = self::scope($model::query());

        return $scoped;
    }

    public static function scope(Builder|Relation $query): Builder|Relation
    {
        $builder = $query instanceof Relation ? $query->getQuery() : $query;
        $user = Auth::user();
        if (! $user || $user->isSuperAdmin()) {
            return $query;
        }
        $model = $builder->getModel();
        $table = $model->getTable();
        if ($user->isClient()) {
            if ($model instanceof Client) {
                $builder->whereKey($user->client_id ?? 0);

                return $query;
            }
            if (in_array($model::class, [JobRequirement::class, ClientSubmission::class, InterviewRecord::class, Offer::class, JoiningRecord::class], true)) {
                $builder->where($table.'.client_id', $user->client_id ?? 0);

                return $query;
            }
            $builder->whereRaw('1 = 0');

            return $query;
        }
        if (! $user->hasRole(['manager', 'team_leader', 'recruiter'])) {
            $builder->whereRaw('1 = 0');

            return $query;
        }
        $staffIds = self::staffIds($user);
        if ($model instanceof User) {
            $builder->whereIn('users.id', $staffIds);

            return $query;
        }
        if ($model instanceof Team) {
            $builder->whereIn('teams.id', self::teamIds($user));

            return $query;
        }
        if ($model instanceof JobRequirement) {
            $builder->where(function (Builder $jobs) use ($user, $staffIds): void {
                $jobs->whereHas('activeAssignments', fn (Builder $assignments) => $assignments->whereIn('recruiter_id', $staffIds));
                if ($user->hasRole(['manager', 'team_leader'])) {
                    $jobs->orWhereIn('assigned_team_id', self::teamIds($user))->orWhere('created_by', $user->id);
                }
            });

            return $query;
        }
        if ($model instanceof Client) {
            $builder->where(function (Builder $clients) use ($user): void {
                $clients->whereHas('jobRequirements', fn (Builder $jobs) => self::scope($jobs));
                if ($user->hasRole(['manager', 'team_leader'])) {
                    $clients->orWhere('account_manager_id', $user->id)->orWhereIn('assigned_team_id', self::teamIds($user))->orWhere('created_by', $user->id);
                }
            });

            return $query;
        }
        if ($model instanceof Candidate) {
            $builder->where(function (Builder $candidates) use ($staffIds): void {
                $candidates->whereIn('assigned_recruiter_id', $staffIds)
                    ->orWhereIn('created_by', $staffIds)
                    ->orWhereHas('applications', fn (Builder $applications) => self::scope($applications));
            });

            return $query;
        }
        if ($model instanceof CandidateApplication) {
            $builder->where(function (Builder $applications) use ($staffIds): void {
                $applications->whereIn('recruiter_id', $staffIds)
                    ->orWhereHas('jobRequirement', fn (Builder $jobs) => self::scope($jobs));
            });

            return $query;
        }
        if ($model instanceof Task) {
            $builder->whereIn($table.'.assigned_to', $staffIds);

            return $query;
        }
        if ($model instanceof CallLog) {
            $builder->where(function (Builder $calls) use ($table, $staffIds): void {
                $calls->whereIn($table.'.recruiter_id', $staffIds)
                    ->orWhereHas('candidate', fn (Builder $candidates) => self::scope($candidates));
            });

            return $query;
        }
        if ($model instanceof ReplacementRecord) {
            $builder->whereHas('jobRequirement', fn (Builder $jobs) => self::scope($jobs));

            return $query;
        }
        if (in_array($model::class, [ClientSubmission::class, InterviewRecord::class, Offer::class, JoiningRecord::class, Placement::class], true)) {
            $builder->whereHas('candidateApplication', fn (Builder $applications) => self::scope($applications));

            return $query;
        }
        $builder->whereRaw('1 = 0');

        return $query;
    }

    public static function authorize(Model $record): void
    {
        abort_unless(self::query($record::class)->whereKey($record->getKey())->exists(), 403, 'This record is outside your assigned workspace.');
    }

    private static function teamIds(User $user): Builder
    {
        return Team::query()->select('teams.id')->where(function (Builder $teams) use ($user): void {
            $teams->where('manager_id', $user->id)->orWhere('team_leader_id', $user->id);
            if ($user->team_id) {
                $teams->orWhere('teams.id', $user->team_id);
            }
            $teams->orWhereHas('members', fn (Builder $members) => $members->where('users.id', $user->id)->where('team_members.is_active', true));
        });
    }

    private static function staffIds(User $user): Builder
    {
        return User::query()->select('users.id')->where(function (Builder $staff) use ($user): void {
            $staff->where('users.id', $user->id);
            if ($user->hasRole(['manager', 'team_leader'])) {
                $staff->orWhereIn('team_id', self::teamIds($user))
                    ->orWhereHas('teams', fn (Builder $teams) => $teams->whereIn('teams.id', self::teamIds($user))->where('team_members.is_active', true));
            }
        });
    }
}
