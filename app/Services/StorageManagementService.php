<?php

namespace App\Services;

use App\Models\AtsNotification;
use App\Models\Organisation;
use App\Models\StorageRecord;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class StorageManagementService
{
    public const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'rtf', 'txt', 'png', 'jpg', 'jpeg', 'csv', 'xlsx',
    ];

    public const ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/rtf',
        'text/rtf',
        'text/plain',
        'image/png',
        'image/jpeg',
        'text/csv',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/octet-stream',
    ];

    public function getQuotaBytes(): int
    {
        $configured = SystemSetting::getValue('storage_quota_bytes');
        if ($configured && (int) $configured > 0) {
            return (int) $configured;
        }

        $org = Organisation::first();

        return $org?->storage_quota_bytes ?? (int) env('ATS_MAX_STORAGE_BYTES', 19510000000);
    }

    public function getStorageMetrics(): array
    {
        $quotaBytes = $this->getQuotaBytes();

        $byCategory = StorageRecord::query()
            ->select('category', DB::raw('SUM(size_bytes) as total_bytes'), DB::raw('COUNT(*) as file_count'))
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        $resumeBytes = (int) ($byCategory->get('resume')->total_bytes ?? 0);
        $jdBytes = (int) ($byCategory->get('jd')->total_bytes ?? 0);
        $offerBytes = (int) ($byCategory->get('offer_letter')->total_bytes ?? 0);
        $agreementBytes = (int) ($byCategory->get('agreement')->total_bytes ?? 0);
        $joiningBytes = (int) ($byCategory->get('joining_doc')->total_bytes ?? 0);
        $backupBytes = (int) ($byCategory->get('backup')->total_bytes ?? 0);
        $otherBytes = (int) ($byCategory->get('other')->total_bytes ?? 0);

        $totalFileBytes = $resumeBytes + $jdBytes + $offerBytes + $agreementBytes + $joiningBytes + $backupBytes + $otherBytes;
        $totalFileCount = (int) StorageRecord::count();

        $dbBytes = $this->estimateDatabaseBytes();
        $totalUsedBytes = $totalFileBytes + $dbBytes;
        $remainingBytes = max(0, $quotaBytes - $totalUsedBytes);
        $utilisationPercent = $quotaBytes > 0 ? round(($totalUsedBytes / $quotaBytes) * 100, 2) : 0.0;

        $monthlyGrowthBytes = (int) StorageRecord::where('created_at', '>=', now()->subDays(30))->sum('size_bytes');

        $alertLevel = 'healthy';
        if ($utilisationPercent >= 95) {
            $alertLevel = 'critical_95';
        } elseif ($utilisationPercent >= 85) {
            $alertLevel = 'high_85';
        } elseif ($utilisationPercent >= 70) {
            $alertLevel = 'warning_70';
        }

        return [
            'quota_bytes' => $quotaBytes,
            'total_used_bytes' => $totalUsedBytes,
            'total_file_bytes' => $totalFileBytes,
            'database_bytes' => $dbBytes,
            'resume_bytes' => $resumeBytes,
            'jd_bytes' => $jdBytes,
            'offer_bytes' => $offerBytes,
            'agreement_bytes' => $agreementBytes,
            'joining_bytes' => $joiningBytes,
            'other_bytes' => $otherBytes + $backupBytes,
            'total_files_count' => $totalFileCount,
            'remaining_bytes' => $remainingBytes,
            'utilisation_percent' => $utilisationPercent,
            'monthly_growth_bytes' => $monthlyGrowthBytes,
            'alert_level' => $alertLevel,
        ];
    }

    public function storePrivateFile(
        UploadedFile $file,
        string $category = 'resume',
        ?string $ownerType = null,
        ?int $ownerId = null
    ): StorageRecord {
        if (! $file->isValid()) {
            throw new RuntimeException('Uploaded file is corrupted or incomplete. Please retry the upload.');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException("File extension [.{$extension}] is not permitted for security reasons.");
        }

        $mime = strtolower((string) $file->getMimeType());
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new RuntimeException("File MIME type [{$mime}] is not permitted.");
        }

        // Basic content safety check against embedded PHP scripts
        $realPath = $file->getRealPath();
        if ($realPath && file_exists($realPath)) {
            $headBytes = (string) file_get_contents($realPath, false, null, 0, 512);
            if (str_contains(strtolower($headBytes), '<?php')) {
                throw new RuntimeException('Security violation: Executable script payload detected in upload.');
            }
        }

        $sizeBytes = (int) $file->getSize();
        $metrics = $this->getStorageMetrics();

        // Reserve 250 MB safety buffer on the hosting account
        $safetyBuffer = 250 * 1024 * 1024;
        if (($metrics['total_used_bytes'] + $sizeBytes) > max(1048576, $metrics['quota_bytes'] - $safetyBuffer) && $metrics['utilisation_percent'] >= 98) {
            throw new RuntimeException('Storage capacity threshold reached. Upload blocked to protect hosting stability.');
        }

        $checksum = $realPath && file_exists($realPath)
            ? hash_file('sha256', $realPath)
            : hash('sha256', $file->get());

        // Deduplicate physical binary storage if an identical verified file already exists in the same category
        $existingRecord = StorageRecord::where('sha256_checksum', $checksum)
            ->where('category', $category)
            ->where('is_quarantined', false)
            ->first();

        $disk = 'local';
        if ($existingRecord && Storage::disk($disk)->exists($existingRecord->file_path)) {
            $storedPath = $existingRecord->file_path;
            $secureFilename = $existingRecord->secure_filename;
            $recordedSize = 0; // Physical binary already stored; avoid double-storing identical resume bytes
        } else {
            $secureFilename = sprintf('%s_%s.%s', now()->format('Ymd_His'), Str::random(24), $extension);
            $directory = 'vault/'.$category.'/'.now()->format('Y/m');
            $storedPath = $file->storeAs($directory, $secureFilename, $disk);
            if (! $storedPath) {
                throw new RuntimeException('Unable to write file to private storage vault.');
            }
            $recordedSize = $sizeBytes;
        }

        $record = StorageRecord::create([
            'disk' => $disk,
            'category' => $category,
            'file_path' => $storedPath,
            'secure_filename' => $secureFilename,
            'original_name' => basename($file->getClientOriginalName()),
            'mime_type' => $mime,
            'extension' => $extension,
            'size_bytes' => $recordedSize > 0 ? $recordedSize : $sizeBytes,
            'sha256_checksum' => $checksum,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'uploaded_by' => Auth::id(),
            'is_quarantined' => false,
            'scan_status' => 'verified',
        ]);

        $this->checkAndNotifyThresholds();

        return $record;
    }

    public function checkAndNotifyThresholds(): ?string
    {
        $metrics = $this->getStorageMetrics();
        $percent = $metrics['utilisation_percent'];

        if ($percent < 70) {
            return null;
        }

        $level = $percent >= 95 ? '95%' : ($percent >= 85 ? '85%' : '70%');

        $admins = User::whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->get();
        foreach ($admins as $admin) {
            AtsNotification::sendToUser(
                $admin,
                "Storage Alert: {$level} Utilisation Reached",
                "CRM storage utilisation is currently at {$percent}%. Review Storage Management for breakdown.",
                'storage_alert',
                '/admin/storage'
            );
        }

        return $level;
    }

    private function estimateDatabaseBytes(): int
    {
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                $dbPath = DB::connection()->getDatabaseName();
                if ($dbPath && $dbPath !== ':memory:' && file_exists($dbPath)) {
                    return (int) filesize($dbPath);
                }
            } elseif ($driver === 'mysql') {
                $dbName = DB::connection()->getDatabaseName();
                $result = DB::selectOne(
                    'SELECT SUM(data_length + index_length) AS size_bytes FROM information_schema.TABLES WHERE table_schema = ?',
                    [$dbName]
                );

                return (int) ($result->size_bytes ?? 0);
            }
        } catch (\Throwable) {
            // Safe fallback if information_schema is restricted
        }

        return 0;
    }
}
