<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\CandidateSkill;
use App\Models\ImportExportLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExportImportService
{
    public function __construct(private readonly CandidateDuplicateService $duplicateService) {}

    /**
     * Prevent spreadsheet formula injection (=, +, -, @, tab, CR) on exported cell values.
     */
    public static function sanitizeCell(mixed $value): string
    {
        $str = trim((string) $value);
        if ($str === '') {
            return '';
        }

        if (preg_match('/^[=+\-@\t\r]/', $str)) {
            return "'".$str;
        }

        return $str;
    }

    /**
     * Generate a formula-safe CSV stream from headers and rows, logging the export in import_export_logs.
     */
    public function generateSafeCsv(string $entityType, string $filename, array $headers, iterable $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, array_map([self::class, 'sanitizeCell'], $headers));

        $rowCount = 0;
        foreach ($rows as $row) {
            fputcsv($handle, array_map([self::class, 'sanitizeCell'], (array) $row));
            $rowCount++;
        }

        rewind($handle);
        $csvContent = (string) stream_get_contents($handle);
        fclose($handle);

        ImportExportLog::create([
            'operation_type' => 'export',
            'entity_type' => $entityType,
            'user_id' => Auth::id(),
            'file_name' => $filename,
            'total_rows' => $rowCount,
            'processed_rows' => $rowCount,
            'status' => 'completed',
        ]);

        AuditService::log('data.exported', $entityType, null, null, [
            'file_name' => $filename,
            'rows_exported' => $rowCount,
        ]);

        return $csvContent;
    }

    /**
     * Process candidate CSV rows in memory-safe batches for shared hosting.
     * Never overwrites existing candidates silently; skips and flags duplicates and invalid rows.
     */
    public function importCandidatesFromRows(array $rows, string $fileName = 'candidates_import.csv'): ImportExportLog
    {
        $totalRows = count($rows);
        $processed = 0;
        $duplicates = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // Account for header row
                $fullName = trim((string) ($row['full_name'] ?? $row['name'] ?? ''));
                $mobile = trim((string) ($row['mobile_number'] ?? $row['mobile'] ?? $row['phone'] ?? ''));
                $email = trim((string) ($row['email'] ?? ''));

                if ($fullName === '' || $mobile === '') {
                    $failed++;
                    $errors[] = "Row {$rowNumber}: Missing mandatory full_name or mobile_number.";

                    continue;
                }

                if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $failed++;
                    $errors[] = "Row {$rowNumber}: Invalid email address [{$email}].";

                    continue;
                }

                $existingDuplicates = $this->duplicateService->findDuplicates($mobile, $email ?: null);
                if ($existingDuplicates->isNotEmpty()) {
                    $duplicates++;
                    $firstMatch = $existingDuplicates->first();
                    $errors[] = "Row {$rowNumber}: Duplicate skipped (matches {$firstMatch->candidate_code} - {$firstMatch->full_name}).";

                    continue;
                }

                $candidate = Candidate::create([
                    'full_name' => $fullName,
                    'mobile_number' => $mobile,
                    'alternate_number' => $row['alternate_number'] ?? null,
                    'email' => $email ?: null,
                    'current_location' => $row['current_location'] ?? 'Mumbai',
                    'preferred_location' => $row['preferred_location'] ?? ($row['current_location'] ?? 'Mumbai'),
                    'country' => $row['country'] ?? 'India',
                    'current_employer' => $row['current_employer'] ?? null,
                    'current_designation' => $row['current_designation'] ?? null,
                    'total_experience' => (float) ($row['total_experience'] ?? 0),
                    'relevant_experience' => (float) ($row['relevant_experience'] ?? ($row['total_experience'] ?? 0)),
                    'qualifications' => $row['qualifications'] ?? null,
                    'industry' => $row['industry'] ?? null,
                    'current_ctc' => isset($row['current_ctc']) && $row['current_ctc'] !== '' ? (float) $row['current_ctc'] : null,
                    'expected_ctc' => isset($row['expected_ctc']) && $row['expected_ctc'] !== '' ? (float) $row['expected_ctc'] : null,
                    'currency' => $row['currency'] ?? 'INR',
                    'notice_period_days' => (int) ($row['notice_period_days'] ?? 30),
                    'source' => $row['source'] ?? 'CSV Bulk Import',
                    'assigned_recruiter_id' => Auth::id(),
                    'created_by' => Auth::id(),
                    'remarks' => $row['remarks'] ?? null,
                ]);

                if (! empty($row['skills'])) {
                    $skillsList = is_array($row['skills'])
                        ? $row['skills']
                        : array_filter(array_map('trim', explode(',', (string) $row['skills'])));

                    foreach (array_unique($skillsList) as $skillName) {
                        CandidateSkill::firstOrCreate([
                            'candidate_id' => $candidate->id,
                            'skill_name' => $skillName,
                        ]);
                    }
                }

                $processed++;
            }

            $log = ImportExportLog::create([
                'operation_type' => 'import',
                'entity_type' => 'candidates',
                'user_id' => Auth::id(),
                'file_name' => $fileName,
                'total_rows' => $totalRows,
                'processed_rows' => $processed,
                'duplicate_rows' => $duplicates,
                'failed_rows' => $failed,
                'error_details' => array_slice($errors, 0, 100),
                'status' => ($failed > 0 || $duplicates > 0) ? 'completed_with_warnings' : 'completed',
            ]);

            AuditService::log('candidates.imported', $log, $log->id, null, [
                'file_name' => $fileName,
                'total_rows' => $totalRows,
                'processed_rows' => $processed,
                'duplicate_rows' => $duplicates,
                'failed_rows' => $failed,
            ]);

            DB::commit();

            return $log;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
