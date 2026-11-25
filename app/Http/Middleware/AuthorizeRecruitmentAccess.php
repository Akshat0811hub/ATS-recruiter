<?php

namespace App\Http\Middleware;

use App\Models\Candidate;
use App\Models\CandidateApplication;
use App\Models\Client;
use App\Models\JobRequirement;
use App\Models\Team;
use App\Models\User;
use App\Services\RecruitmentAccess;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeRecruitmentAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()->getName();
        $permission = match (true) {
            str_starts_with($name, 'clients.') => in_array($name, ['clients.index', 'clients.show'], true) ? 'clients.view' : 'clients.manage',
            $name === 'jobs.store', $name === 'jobs.duplicate' => 'jobs.create',
            $name === 'jobs.assign' => 'jobs.assign',
            str_starts_with($name, 'jobs.') => $request->isMethod('GET') ? 'jobs.view' : 'jobs.update',
            $name === 'candidates.calls.store' => 'calls.record',
            $name === 'candidates.store' => 'candidates.create',
            $name === 'candidates.export' => 'candidates.export',
            $name === 'candidates.import', $name === 'candidates.import-template' => 'candidates.import',
            $name === 'candidates.merge' => 'candidates.merge',
            str_starts_with($name, 'candidates.') => $request->isMethod('GET') ? 'candidates.view' : 'candidates.update',
            str_starts_with($name, 'tasks.') => 'tasks.manage',
            $name === 'applications.screening.store' => 'screening.perform',
            $name === 'applications.submit', str_starts_with($name, 'submissions.') => $name === 'submissions.decision' ? 'submissions.decide' : 'submissions.create',
            $name === 'applications.interviews.schedule', $name === 'interviews.index' => 'interviews.schedule',
            $name === 'interviews.result' => 'interviews.feedback',
            $name === 'interviews.override' => 'interviews.override',
            $name === 'applications.offer.store', $name === 'offers.index' => 'offers.manage',
            $name === 'joinings.replacement' => 'replacements.reopen',
            str_starts_with($name, 'joinings.') => 'joinings.manage',
            str_starts_with($name, 'applications.'), $name === 'pipeline.index' => 'candidates.view',
            $name === 'reports.export' => 'reports.export',
            $name === 'reports.index', str_starts_with($name, 'management.') => 'reports.view',
            default => null,
        };
        if ($permission) {
            abort_unless($request->user()->hasPermission($permission), 403, 'Your role does not allow this action.');
        }
        foreach ($request->route()->parameters() as $record) {
            if ($record instanceof Model && in_array($record::class, RecruitmentAccess::MODELS, true)) {
                RecruitmentAccess::authorize($record);
            }
        }
        if (! $request->isMethod('GET')) {
            foreach ([
                'duplicate_candidate_id' => Candidate::class, 'primary_recruiter_id' => User::class,
                'candidate_id' => Candidate::class, 'source_candidate_id' => Candidate::class,
                'target_candidate_id' => Candidate::class, 'job_requirement_id' => JobRequirement::class,
                'client_id' => Client::class, 'assigned_to' => User::class, 'recruiter_id' => User::class,
                'assigned_team_id' => Team::class, 'candidate_application_id' => CandidateApplication::class,
            ] as $field => $model) {
                $id = $request->input($field);
                if (is_scalar($id) && ctype_digit((string) $id) && (int) $id > 0) {
                    $record = $model::find($id);
                    if ($record) {
                        RecruitmentAccess::authorize($record);
                    }
                }
            }
            foreach (['application_ids' => CandidateApplication::class, 'recruiter_ids' => User::class, 'secondary_recruiter_ids' => User::class] as $field => $model) {
                $ids = $request->input($field, []);
                if (is_array($ids)) {
                    foreach ($ids as $id) {
                        if (is_scalar($id) && ctype_digit((string) $id) && ($record = $model::find($id))) {
                            RecruitmentAccess::authorize($record);
                        }
                    }
                }
            }
        }

        return $next($request);
    }
}
