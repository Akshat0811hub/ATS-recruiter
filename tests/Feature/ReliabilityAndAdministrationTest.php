<?php

namespace Tests\Feature;

use App\Models\AtsNotification;
use App\Models\Candidate;
use App\Models\StorageRecord;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BackupService;
use App\Services\EmailService;
use App\Services\ExportImportService;
use App\Services\StorageManagementService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ReliabilityAndAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        (new DatabaseSeeder)->seedCoreFoundation();
        $this->superAdmin = User::where('email', 'admin@orbitouch-hr.com')->firstOrFail();
    }

    /**
     * Scenario 20: A resume upload fails midway or contains dangerous payload.
     */
    public function test_scenario_20_corrupted_or_unsafe_resume_upload_is_rejected_cleanly(): void
    {
        $storageService = app(StorageManagementService::class);
        $dangerousFile = UploadedFile::fake()->createWithContent('malicious.php', '<?php system("whoami"); ?>');

        $this->expectException(RuntimeException::class);
        $storageService->storePrivateFile($dangerousFile, 'resume');
    }

    /**
     * Scenario 24 & 27: Encrypted backup is restored and deleted records are recovered by an authorised user.
     */
    public function test_scenarios_24_and_27_encrypted_backup_restoration_and_deleted_record_recovery(): void
    {
        $candidate = Candidate::create([
            'full_name' => 'Neha Kulkarni',
            'mobile_number' => '+91 9844455566',
            'email' => 'neha.kulkarni@example.com',
        ]);

        // Create encrypted backup
        $backupService = app(BackupService::class);
        $backupLog = $backupService->createEncryptedBackup('full');
        $this->assertTrue($backupLog->is_encrypted);
        $this->assertTrue($backupLog->integrity_verified);

        // Scenario 27: Soft-delete candidate and restore via authorised recovery controller
        $candidate->delete();
        $this->assertSoftDeleted('candidates', ['id' => $candidate->id]);

        $this->actingAs($this->superAdmin)->post('/admin/recovery/restore', [
            'type' => 'candidate',
            'id' => $candidate->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('candidates', ['id' => $candidate->id, 'deleted_at' => null]);

        // Scenario 24: Hard delete and restore from encrypted backup
        Candidate::where('id', $candidate->id)->forceDelete();
        $this->assertDatabaseMissing('candidates', ['id' => $candidate->id]);

        $summary = $backupService->verifyAndRestoreBackup($backupLog);
        $this->assertTrue($summary['integrity_verified']);
        $this->assertDatabaseHas('candidates', ['id' => $candidate->id, 'full_name' => 'Neha Kulkarni']);
    }

    /**
     * Scenario 25: Storage reaches its configured warning threshold (70%, 85%, 95%).
     */
    public function test_scenario_25_storage_reaching_warning_threshold_emits_alert(): void
    {
        // Set quota to 1000 bytes and store 800 bytes (80% > 70% warning threshold)
        SystemSetting::setValue('storage_quota_bytes', 1000, 'storage', 'integer');

        StorageRecord::create([
            'disk' => 'local',
            'category' => 'resume',
            'file_path' => 'vault/resume/sample.pdf',
            'secure_filename' => 'sample.pdf',
            'original_name' => 'sample.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 800,
            'sha256_checksum' => hash('sha256', 'test'),
        ]);

        $storageService = app(StorageManagementService::class);
        $alertLevel = $storageService->checkAndNotifyThresholds();

        $this->assertNotNull($alertLevel);
        $this->assertTrue(
            AtsNotification::where('notifiable_id', $this->superAdmin->id)
                ->where('category', 'storage_alert')
                ->exists()
        );
    }

    /**
     * Scenario 26: An email fails to send and is logged accurately without crashing the workflow.
     */
    public function test_scenario_26_email_failure_is_caught_and_logged_accurately(): void
    {
        Mail::shouldReceive('html')->once()->andThrow(new RuntimeException('SMTP connection timed out'));

        $emailService = app(EmailService::class);
        $log = $emailService->sendTemplatedEmail(
            'interview_invitation',
            'candidate@example.com',
            'Test Candidate',
            ['candidate_name' => 'Test Candidate']
        );

        $this->assertEquals('failed', $log->delivery_status);
        $this->assertStringContainsString('SMTP connection timed out', (string) $log->smtp_response);
    }

    /**
     * Scenario 29: A large candidate import is processed safely with duplicate detection and formula sanitization.
     */
    public function test_scenario_29_large_candidate_import_and_formula_safe_export(): void
    {
        $exportImport = app(ExportImportService::class);

        $rows = [];
        for ($i = 1; $i <= 60; $i++) {
            $rows[] = [
                'full_name' => "Imported Candidate {$i}",
                'mobile_number' => sprintf('98555%05d', $i),
                'email' => "imported{$i}@example.com",
                'current_location' => 'Mumbai',
                'total_experience' => 4.5,
                'skills' => 'PHP, Laravel, MySQL',
            ];
        }
        // Add 1 duplicate row and 1 invalid row
        $rows[] = [
            'full_name' => 'Duplicate Candidate 1',
            'mobile_number' => '9855500001',
            'email' => 'dup@example.com',
        ];
        $rows[] = [
            'full_name' => '',
            'mobile_number' => '',
        ];

        $this->actingAs($this->superAdmin);
        $log = $exportImport->importCandidatesFromRows($rows, 'bulk_62_rows.csv');

        $this->assertEquals(62, $log->total_rows);
        $this->assertEquals(60, $log->processed_rows);
        $this->assertEquals(1, $log->duplicate_rows);
        $this->assertEquals(1, $log->failed_rows);
        $this->assertEquals(60, Candidate::count());

        // Verify formula injection sanitization
        $this->assertEquals("'=CMD|' /C calc'!A0", ExportImportService::sanitizeCell("=CMD|' /C calc'!A0"));
    }

    /**
     * Scenario 30: A software update / migration runs without losing existing records.
     */
    public function test_scenario_30_software_update_migration_preserves_existing_records(): void
    {
        $cand = Candidate::create([
            'full_name' => 'Persistent Production Candidate',
            'mobile_number' => '+91 9899900011',
            'email' => 'persistent@orbitouch-hr.com',
        ]);

        // Run non-destructive production migration command (`php artisan migrate --force`)
        Artisan::call('migrate', ['--force' => true]);

        $this->assertDatabaseHas('candidates', [
            'id' => $cand->id,
            'full_name' => 'Persistent Production Candidate',
            'normalized_mobile' => '9899900011',
        ]);
    }
}
