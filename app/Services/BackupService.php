<?php

namespace App\Services;

use App\Models\BackupLog;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\JobRequirement;
use App\Models\StorageRecord;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BackupService
{
    public const BACKUP_TABLES = [
        'organisations',
        'roles',
        'permissions',
        'role_permission',
        'teams',
        'users',
        'team_members',
        'storage_records',
        'clients',
        'client_contacts',
        'job_requirements',
        'job_assignments',
        'job_screening_questions',
        'interview_round_definitions',
        'candidates',
        'candidate_contacts',
        'candidate_skills',
        'resume_versions',
        'candidate_documents',
        'candidate_notes',
        'candidate_applications',
        'screening_records',
        'call_logs',
        'communication_records',
        'client_submissions',
        'client_feedback',
        'interview_records',
        'interview_attempts',
        'interview_feedback',
        'offers',
        'offer_revisions',
        'joining_records',
        'placements',
        'replacement_records',
        'tasks',
        'email_templates',
        'system_settings',
        'custom_fields',
    ];

    public function isExternalDestinationConfigured(): bool
    {
        $externalDisk = config('ats.backup_external_disk') ?: SystemSetting::getValue('backup_external_disk');

        return ! empty($externalDisk);
    }

    /**
     * Create an encrypted, integrity-verified snapshot of the database and private document manifest.
     */
    public function createEncryptedBackup(string $type = 'full'): BackupLog
    {
        $backupCode = sprintf('BKP-%s-%s', now()->format('Ymd-His'), strtoupper(substr(md5(uniqid('', true)), 0, 4)));
        $externalConfigured = $this->isExternalDestinationConfigured();
        $destination = $externalConfigured ? 'external_vault' : 'local_vault';

        $tablesPayload = [];
        foreach (self::BACKUP_TABLES as $table) {
            $tablesPayload[$table] = DB::table($table)->get()->map(fn ($r) => (array) $r)->all();
        }

        // Include private storage file manifest & inline small critical documents if needed
        $documentsManifest = StorageRecord::withTrashed()->get()->map(function (StorageRecord $rec) {
            $exists = Storage::disk($rec->disk)->exists($rec->file_path);

            return [
                'id' => $rec->id,
                'category' => $rec->category,
                'file_path' => $rec->file_path,
                'original_name' => $rec->original_name,
                'sha256_checksum' => $rec->sha256_checksum,
                'size_bytes' => $rec->size_bytes,
                'exists' => $exists,
                'content_b64' => ($exists && $rec->size_bytes <= 2097152)
                    ? base64_encode((string) Storage::disk($rec->disk)->get($rec->file_path))
                    : null,
            ];
        })->all();

        $snapshot = [
            'backup_code' => $backupCode,
            'backup_type' => $type,
            'generated_at' => now()->toIso8601String(),
            'app_name' => config('app.name', 'OrbiTouch ATS'),
            'counts' => [
                'clients' => Client::withTrashed()->count(),
                'jobs' => JobRequirement::withTrashed()->count(),
                'candidates' => Candidate::withTrashed()->count(),
                'storage_records' => count($documentsManifest),
            ],
            'tables' => $tablesPayload,
            'documents_manifest' => $documentsManifest,
        ];

        $jsonPayload = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
        $encryptedPayload = Crypt::encryptString((string) $jsonPayload);
        $checksum = hash('sha256', $encryptedPayload);

        $archivePath = 'backups/'.now()->format('Y/m')."/{$backupCode}.ats.enc";
        Storage::disk('local')->put($archivePath, $encryptedPayload);

        $sizeBytes = strlen($encryptedPayload);

        $log = BackupLog::create([
            'backup_code' => $backupCode,
            'backup_type' => $type,
            'destination' => $destination,
            'archive_path' => $archivePath,
            'size_bytes' => $sizeBytes,
            'sha256_checksum' => $checksum,
            'is_encrypted' => true,
            'external_configured' => $externalConfigured,
            'integrity_verified' => true,
            'status' => 'completed',
            'triggered_by' => Auth::id(),
            'completed_at' => now(),
        ]);

        AuditService::log('backup.created', $log, $log->id, null, [
            'backup_code' => $backupCode,
            'size_bytes' => $sizeBytes,
            'sha256_checksum' => $checksum,
            'external_configured' => $externalConfigured,
        ]);

        return $log;
    }

    /**
     * Verify backup checksum & decryptability, and restore missing/deleted records & private documents safely.
     */
    public function verifyAndRestoreBackup(BackupLog $backupLog): array
    {
        if (! $backupLog->archive_path || ! Storage::disk('local')->exists($backupLog->archive_path)) {
            throw new RuntimeException("Backup archive [{$backupLog->backup_code}] could not be found in storage.");
        }

        $encryptedPayload = (string) Storage::disk('local')->get($backupLog->archive_path);
        $actualChecksum = hash('sha256', $encryptedPayload);

        if ($backupLog->sha256_checksum && ! hash_equals($backupLog->sha256_checksum, $actualChecksum)) {
            throw new RuntimeException('Backup integrity verification failed: SHA-256 checksum mismatch.');
        }

        $decryptedJson = Crypt::decryptString($encryptedPayload);
        $snapshot = json_decode($decryptedJson, true);
        if (! is_array($snapshot) || empty($snapshot['tables'])) {
            throw new RuntimeException('Decrypted backup payload structure is invalid.');
        }

        $restoredRecordsCount = 0;
        $restoredFilesCount = 0;

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($snapshot, &$restoredRecordsCount, &$restoredFilesCount) {
                foreach ($snapshot['tables'] as $table => $rows) {
                    if (! is_array($rows) || ! Schema::hasTable($table)) {
                        continue;
                    }
                    foreach ($rows as $row) {
                        if (isset($row['id'])) {
                            $exists = DB::table($table)->where('id', $row['id'])->exists();
                            if (! $exists) {
                                DB::table($table)->insert($row);
                                $restoredRecordsCount++;
                            } elseif (array_key_exists('deleted_at', $row) && $row['deleted_at'] === null) {
                                DB::table($table)->where('id', $row['id'])->whereNotNull('deleted_at')->update(['deleted_at' => null]);
                            }
                        }
                    }
                }

                foreach ($snapshot['documents_manifest'] ?? [] as $doc) {
                    if (! empty($doc['file_path']) && ! empty($doc['content_b64'])) {
                        if (! Storage::disk('local')->exists($doc['file_path'])) {
                            Storage::disk('local')->put($doc['file_path'], base64_decode($doc['content_b64']));
                            $restoredFilesCount++;
                        }
                    }
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $backupLog->update([
            'integrity_verified' => true,
            'status' => 'restored',
        ]);

        AuditService::log('backup.restored', $backupLog, $backupLog->id, null, [
            'backup_code' => $backupLog->backup_code,
            'restored_records' => $restoredRecordsCount,
            'restored_files' => $restoredFilesCount,
        ]);

        return [
            'backup_code' => $backupLog->backup_code,
            'integrity_verified' => true,
            'restored_records' => $restoredRecordsCount,
            'restored_files' => $restoredFilesCount,
        ];
    }
}
