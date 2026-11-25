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
     * Normalize an imported CSV cell by trimming whitespace and stripping a leading
     * spreadsheet-protection apostrophe before +, -, =, or @.
     */
    public static function cleanImportedCell(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }

        if (str_starts_with($str, "'") && strlen($str) > 1 && preg_match("/^'[=+\\-@]/", $str)) {
            $str = substr($str, 1);
        }

        return trim($str) !== '' ? trim($str) : null;
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
                $fullName = self::cleanImportedCell($row['full_name'] ?? $row['candidate_name'] ?? $row['name'] ?? null);
                $mobile = self::cleanImportedCell($row['mobile_number'] ?? $row['mobile'] ?? $row['phone'] ?? $row['phone_number'] ?? null);
                $email = self::cleanImportedCell($row['email'] ?? $row['email_address'] ?? null);

                if ($fullName === null || $mobile === null) {
                    $failed++;
                    $errors[] = "Row {$rowNumber}: Missing required full_name or mobile_number.";

                    continue;
                }

                if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $failed++;
                    $errors[] = "Row {$rowNumber}: Invalid email address ({$email}).";

                    continue;
                }

                $totalExpRaw = self::cleanImportedCell($row['total_experience'] ?? $row['total_experience_(yrs)'] ?? null);
                $relevantExpRaw = self::cleanImportedCell($row['relevant_experience'] ?? $row['relevant_experience_(yrs)'] ?? null);
                $currentCtcRaw = self::cleanImportedCell($row['current_ctc'] ?? null);
                $expectedCtcRaw = self::cleanImportedCell($row['expected_ctc'] ?? null);
                $noticeDaysRaw = self::cleanImportedCell($row['notice_period_days'] ?? $row['notice_period_(days)'] ?? null);

                $numericValid = true;
                foreach ([
                    'total_experience' => $totalExpRaw,
                    'relevant_experience' => $relevantExpRaw,
                    'current_ctc' => $currentCtcRaw,
                    'expected_ctc' => $expectedCtcRaw,
                    'notice_period_days' => $noticeDaysRaw,
                ] as $fieldLabel => $rawVal) {
                    if ($rawVal !== null && (! is_numeric($rawVal) || (float) $rawVal < 0)) {
                        $failed++;
                        $errors[] = "Row {$rowNumber}: {$fieldLabel} must be a non-negative number.";
                        $numericValid = false;
                        break;
                    }
                }

                if (! $numericValid) {
                    continue;
                }

                $existingDuplicates = $this->duplicateService->findDuplicates($mobile, $email);
                if ($existingDuplicates->isNotEmpty()) {
                    $duplicates++;
                    $errors[] = "Row {$rowNumber}: Duplicate skipped; a candidate with matching contact details already exists.";

                    continue;
                }

                $consentInput = strtolower((string) (self::cleanImportedCell($row['consent_status'] ?? null) ?? 'pending'));
                $consentStatus = in_array($consentInput, ['granted', 'pending', 'withdrawn'], true) ? $consentInput : 'pending';

                $totalExperience = $totalExpRaw !== null ? (float) $totalExpRaw : 0.0;
                $relevantExperience = $relevantExpRaw !== null ? (float) $relevantExpRaw : $totalExperience;

                $candidate = Candidate::create([
                    'full_name' => $fullName,
                    'mobile_number' => $mobile,
                    'alternate_number' => self::cleanImportedCell($row['alternate_number'] ?? null),
                    'email' => $email,
                    'current_location' => self::cleanImportedCell($row['current_location'] ?? null),
                    'preferred_location' => self::cleanImportedCell($row['preferred_location'] ?? null),
                    'country' => self::cleanImportedCell($row['country'] ?? null) ?? 'India',
                    'current_employer' => self::cleanImportedCell($row['current_employer'] ?? null),
                    'current_designation' => self::cleanImportedCell($row['current_designation'] ?? null),
                    'total_experience' => $totalExperience,
                    'relevant_experience' => $relevantExperience,
                    'qualifications' => self::cleanImportedCell($row['qualifications'] ?? null),
                    'industry' => self::cleanImportedCell($row['industry'] ?? null),
                    'current_ctc' => $currentCtcRaw !== null ? (float) $currentCtcRaw : null,
                    'expected_ctc' => $expectedCtcRaw !== null ? (float) $expectedCtcRaw : null,
                    'currency' => self::cleanImportedCell($row['currency'] ?? null) ?? 'INR',
                    'notice_period_days' => $noticeDaysRaw !== null ? (int) round((float) $noticeDaysRaw) : null,
                    'source' => self::cleanImportedCell($row['source'] ?? null) ?? 'CSV Bulk Import',
                    'consent_status' => $consentStatus,
                    'consent_recorded_at' => $consentStatus === 'granted' ? now() : null,
                    'assigned_recruiter_id' => Auth::id(),
                    'created_by' => Auth::id(),
                    'remarks' => self::cleanImportedCell($row['remarks'] ?? null),
                ]);

                if (! empty($row['skills'])) {
                    $skillsList = is_array($row['skills'])
                        ? $row['skills']
                        : array_filter(array_map('trim', explode(',', (string) $row['skills'])));

                    foreach (array_unique($skillsList) as $skillName) {
                        $cleanedSkill = self::cleanImportedCell($skillName);
                        if ($cleanedSkill !== null) {
                            CandidateSkill::firstOrCreate([
                                'candidate_id' => $candidate->id,
                                'skill_name' => $cleanedSkill,
                            ]);
                        }
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
