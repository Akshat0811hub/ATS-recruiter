<?php

namespace App\Http\Controllers;

use App\Models\CandidateDocument;
use App\Models\Client;
use App\Models\ClientSubmission;
use App\Models\JobRequirement;
use App\Models\JoiningRecord;
use App\Models\Offer;
use App\Models\ResumeVersion;
use App\Models\StorageRecord;
use App\Services\AuditService;
use App\Services\RecruitmentAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function download(StorageRecord $storageRecord)
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        // Strict Client Organisation Isolation for document downloads
        if ($user->isClient()) {
            $clientId = (int) $user->client_id;
            $authorized = false;

            if ($storageRecord->category === 'resume') {
                // Client can only download the exact resume version explicitly submitted to their organisation
                $resumeIds = ResumeVersion::where('storage_record_id', $storageRecord->id)->pluck('id');

                $authorized = $resumeIds->isNotEmpty() && ClientSubmission::where('client_id', $clientId)
                    ->whereIn('resume_version_id', $resumeIds)
                    ->exists();
            } elseif ($storageRecord->category === 'jd') {
                $authorized = JobRequirement::where('client_id', $clientId)
                    ->where('jd_storage_id', $storageRecord->id)
                    ->exists();
            } elseif ($storageRecord->category === 'offer_letter') {
                $authorized = Offer::where('client_id', $clientId)
                    ->where('offer_letter_storage_id', $storageRecord->id)
                    ->exists();
            } elseif ($storageRecord->category === 'joining_doc') {
                $authorized = JoiningRecord::where('client_id', $clientId)
                    ->where('joining_document_storage_id', $storageRecord->id)
                    ->exists();
            } elseif ($storageRecord->category === 'agreement') {
                $authorized = Client::where('id', $clientId)
                    ->where('agreement_storage_id', $storageRecord->id)
                    ->exists();
            } elseif ($storageRecord->category === 'other') {
                $docCandidateIds = CandidateDocument::where('storage_record_id', $storageRecord->id)
                    ->where('is_client_visible', true)
                    ->pluck('candidate_id');

                $authorized = $docCandidateIds->isNotEmpty() && ClientSubmission::where('client_id', $clientId)
                    ->whereIn('candidate_id', $docCandidateIds)
                    ->exists();
            }

            if (! $authorized) {
                AuditService::log('document.unauthorised_access_blocked', $storageRecord, $storageRecord->id);
                abort(403, 'Access denied: You are not authorised to access this confidential document.');
            }
        } else {
            if ($storageRecord->category === 'agreement' && ! $user->hasRole(['super_admin', 'manager'])) {
                abort(403, 'Confidential client agreements are restricted to Super Admin and Account Managers.');
            }

            if (! $user->isSuperAdmin()) {
                $allowed = match ($storageRecord->category) {
                    'resume' => ResumeVersion::where('storage_record_id', $storageRecord->id)
                        ->whereHas('candidate', fn ($query) => RecruitmentAccess::scope($query))
                        ->exists(),
                    'jd' => RecruitmentAccess::query(JobRequirement::class)
                        ->where('jd_storage_id', $storageRecord->id)
                        ->exists(),
                    'offer_letter' => RecruitmentAccess::query(Offer::class)
                        ->where('offer_letter_storage_id', $storageRecord->id)
                        ->exists(),
                    'joining_doc' => RecruitmentAccess::query(JoiningRecord::class)
                        ->where('joining_document_storage_id', $storageRecord->id)
                        ->exists(),
                    'agreement' => RecruitmentAccess::query(Client::class)
                        ->where('agreement_storage_id', $storageRecord->id)
                        ->exists(),
                    'other' => CandidateDocument::where('storage_record_id', $storageRecord->id)
                        ->whereHas('candidate', fn ($query) => RecruitmentAccess::scope($query))
                        ->exists(),
                    default => false,
                };
                abort_unless($allowed, 403, 'This document is outside your assigned workspace.');
            }
        }

        if (! Storage::disk($storageRecord->disk)->exists($storageRecord->file_path)) {
            abort(404, 'The requested file is no longer available in the private storage vault.');
        }

        AuditService::log('document.downloaded', $storageRecord, $storageRecord->id, null, [
            'original_name' => $storageRecord->original_name,
            'category' => $storageRecord->category,
        ]);

        return Storage::disk($storageRecord->disk)->download(
            $storageRecord->file_path,
            $storageRecord->original_name,
            [
                'Content-Type' => $storageRecord->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
