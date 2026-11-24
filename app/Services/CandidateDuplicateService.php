<?php

namespace App\Services;

use App\Models\Candidate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CandidateDuplicateService
{
    /**
     * Detect potential duplicate candidates using normalized mobile, normalized email,
     * normalized alternate contact, and SHA-256 resume checksum.
     */
    public function findDuplicates(
        ?string $mobileNumber = null,
        ?string $email = null,
        ?string $alternateNumber = null,
        ?string $resumeChecksum = null,
        ?int $excludeCandidateId = null
    ): Collection {
        $normMobile = Candidate::normalizePhone($mobileNumber);
        $normEmail = Candidate::normalizeEmail($email);
        $normAlt = Candidate::normalizePhone($alternateNumber);

        if (! $normMobile && ! $normEmail && ! $normAlt && ! $resumeChecksum) {
            return collect();
        }

        $query = Candidate::query()->with(['assignedRecruiter', 'applications.jobRequirement']);

        if ($excludeCandidateId) {
            $query->where('id', '!=', $excludeCandidateId);
        }

        $query->where(function ($q) use ($normMobile, $normEmail, $normAlt, $resumeChecksum) {
            if ($normMobile) {
                $q->orWhere('normalized_mobile', $normMobile)
                    ->orWhere('normalized_alternate', $normMobile);
            }
            if ($normAlt) {
                $q->orWhere('normalized_mobile', $normAlt)
                    ->orWhere('normalized_alternate', $normAlt);
            }
            if ($normEmail) {
                $q->orWhere('normalized_email', $normEmail);
            }
            if ($resumeChecksum) {
                $q->orWhere('primary_resume_checksum', $resumeChecksum)
                    ->orWhereHas('resumeVersions', fn ($rq) => $rq->where('sha256_checksum', $resumeChecksum));
            }
        });

        return $query->limit(15)->get()->map(function (Candidate $candidate) use ($normMobile, $normEmail, $normAlt, $resumeChecksum) {
            $reasons = [];
            if ($normMobile && ($candidate->normalized_mobile === $normMobile || $candidate->normalized_alternate === $normMobile)) {
                $reasons[] = 'Mobile Number Match';
            }
            if ($normAlt && ($candidate->normalized_mobile === $normAlt || $candidate->normalized_alternate === $normAlt)) {
                $reasons[] = 'Alternate Number Match';
            }
            if ($normEmail && $candidate->normalized_email === $normEmail) {
                $reasons[] = 'Email Address Match';
            }
            if ($resumeChecksum && $candidate->primary_resume_checksum === $resumeChecksum) {
                $reasons[] = 'Identical Resume Checksum';
            }

            $candidate->setAttribute('duplicate_reasons', $reasons);

            return $candidate;
        });
    }

    /**
     * Safely merge a duplicate secondary candidate into a primary master candidate
     * without losing any job applications, call history, notes, or resume versions.
     */
    public function mergeCandidates(Candidate $master, Candidate $duplicate, string $reason = 'Administrator duplicate merge'): Candidate
    {
        return DB::transaction(function () use ($master, $duplicate, $reason) {
            // Fill any blank fields on master from duplicate
            $fillableIfEmpty = [
                'alternate_number', 'email', 'current_location', 'preferred_location',
                'current_employer', 'current_designation', 'qualifications', 'industry',
                'current_ctc', 'expected_ctc', 'primary_resume_checksum',
            ];

            foreach ($fillableIfEmpty as $field) {
                if (empty($master->{$field}) && ! empty($duplicate->{$field})) {
                    $master->{$field} = $duplicate->{$field};
                }
            }

            if (empty($master->alternate_number) && $duplicate->mobile_number !== $master->mobile_number) {
                $master->alternate_number = $duplicate->mobile_number;
            }

            $master->save();

            // Re-link applications that don't conflict with existing master applications for the same job
            foreach ($duplicate->applications as $dupApp) {
                $existsOnMaster = $master->applications()
                    ->where('job_requirement_id', $dupApp->job_requirement_id)
                    ->exists();

                if (! $existsOnMaster) {
                    $dupApp->update(['candidate_id' => $master->id]);
                }
            }

            // Re-link call logs, notes, documents, submissions, interviews, offers, joinings, tasks
            $duplicate->callLogs()->update(['candidate_id' => $master->id]);
            $duplicate->notes()->update(['candidate_id' => $master->id]);
            $duplicate->documents()->update(['candidate_id' => $master->id]);
            $duplicate->tasks()->update(['candidate_id' => $master->id]);
            $duplicate->interviewRecords()->update(['candidate_id' => $master->id]);
            $duplicate->offers()->update(['candidate_id' => $master->id]);
            $duplicate->joiningRecords()->update(['candidate_id' => $master->id]);

            foreach ($duplicate->clientSubmissions as $dupSub) {
                $subConflict = $master->clientSubmissions()
                    ->where('job_requirement_id', $dupSub->job_requirement_id)
                    ->exists();
                if (! $subConflict) {
                    $dupSub->update(['candidate_id' => $master->id]);
                }
            }

            // Transfer resume versions with incremented version numbers
            $maxVersion = (int) $master->resumeVersions()->max('version_number');
            foreach ($duplicate->resumeVersions as $rv) {
                $maxVersion++;
                $rv->update([
                    'candidate_id' => $master->id,
                    'version_number' => $maxVersion,
                    'is_primary' => false,
                ]);
            }

            $duplicate->update(['merged_into_candidate_id' => $master->id]);
            $duplicate->delete();

            AuditService::log(
                'candidate.merged',
                $master,
                $master->id,
                ['duplicate_candidate_id' => $duplicate->id, 'duplicate_code' => $duplicate->candidate_code],
                ['master_candidate_id' => $master->id, 'master_code' => $master->candidate_code],
                $reason
            );

            AuditService::recordActivity(
                $master,
                'candidate_merged',
                "Merged duplicate record {$duplicate->candidate_code} into {$master->candidate_code}",
                $reason
            );

            return $master->fresh();
        });
    }
}
