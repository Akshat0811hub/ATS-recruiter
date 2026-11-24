<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientSubmission;
use App\Models\JobRequirement;
use App\Models\Offer;
use App\Models\ResumeVersion;
use App\Models\StorageRecord;
use App\Services\AuditService;
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
                // Client can only download a resume if it was submitted to their client organisation
                $resumeIds = ResumeVersion::where('storage_record_id', $storageRecord->id)->pluck('id');
                $candidateIds = ResumeVersion::where('storage_record_id', $storageRecord->id)->pluck('candidate_id');

                $authorized = ClientSubmission::where('client_id', $clientId)
                    ->where(function ($q) use ($resumeIds, $candidateIds) {
                        $q->whereIn('resume_version_id', $resumeIds)
                            ->orWhereIn('candidate_id', $candidateIds);
                    })
                    ->exists();
            } elseif ($storageRecord->category === 'jd') {
                $authorized = JobRequirement::where('client_id', $clientId)
                    ->where('jd_storage_id', $storageRecord->id)
                    ->exists();
            } elseif ($storageRecord->category === 'offer_letter') {
                $authorized = Offer::where('client_id', $clientId)
                    ->where('offer_letter_storage_id', $storageRecord->id)
                    ->exists();
            } elseif ($storageRecord->category === 'agreement') {
                $authorized = Client::where('id', $clientId)
                    ->where('agreement_storage_id', $storageRecord->id)
                    ->exists();
            }

            if (! $authorized) {
                AuditService::log('document.unauthorised_access_blocked', $storageRecord, $storageRecord->id);
                abort(403, 'Access denied: You are not authorised to access another client organisation\'s confidential documents.');
            }
        } else {
            // Internal staff: agreements are restricted to Super Admin & Manager
            if ($storageRecord->category === 'agreement' && ! $user->hasRole(['super_admin', 'manager'])) {
                abort(403, 'Confidential client agreements are restricted to Super Admin and Account Managers.');
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
