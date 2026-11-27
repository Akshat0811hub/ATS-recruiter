<?php

use App\Models\AtsNotification;
use App\Models\InterviewRecord;
use App\Models\Task;
use App\Models\User;
use App\Services\BackupService;
use App\Services\StorageManagementService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('ats:backup {--type=full}', function (BackupService $backupService) {
    $log = $backupService->createEncryptedBackup((string) $this->option('type'));
    $this->info("Encrypted backup [{$log->backup_code}] completed ({$log->size_bytes} bytes).");
})->purpose('Create an encrypted daily backup of OrbiTouch ATS database and private document vault');

Artisan::command('ats:storage-check', function (StorageManagementService $storageService) {
    $level = $storageService->checkAndNotifyThresholds();
    $metrics = $storageService->getStorageMetrics();
    $this->info("Storage utilisation: {$metrics['utilisation_percent']}% (Alert: ".($level ?? 'None').').');
})->purpose('Inspect Hostinger 20 GB storage utilisation and emit 70%/85%/95% alerts');

Artisan::command('ats:send-reminders', function () {
    $today = Carbon::today()->toDateString();

    $overdueTasks = Task::whereIn('status', ['pending', 'in_progress'])
        ->whereDate('due_date', '<', $today)
        ->limit(50)
        ->get();

    foreach ($overdueTasks as $task) {
        if (! $task->assigned_to) {
            continue;
        }
        $title = "Overdue Follow-up: {$task->title}";
        $alreadySent = AtsNotification::where('notifiable_type', User::class)
            ->where('notifiable_id', $task->assigned_to)
            ->where('category', 'overdue_followup')
            ->whereDate('created_at', $today)
            ->get()
            ->contains(fn (AtsNotification $n) => ($n->data['title'] ?? '') === $title);

        if (! $alreadySent) {
            AtsNotification::sendToUser(
                $task->assigned_to,
                $title,
                "Task was due on {$task->due_date->format('d M Y')}.",
                'overdue_followup',
                '/tasks?filter=overdue'
            );
        }
    }

    $todaysInterviews = InterviewRecord::with(['candidate', 'jobRequirement', 'candidateApplication'])
        ->whereDate('interview_date', $today)
        ->whereIn('status', ['scheduled', 'rescheduled'])
        ->get();

    foreach ($todaysInterviews as $interview) {
        $recruiterId = $interview->candidateApplication?->recruiter_id;
        if ($recruiterId) {
            $title = "Today's Interview: {$interview->round_name} ({$interview->interview_code})";
            $alreadySent = AtsNotification::where('notifiable_type', User::class)
                ->where('notifiable_id', $recruiterId)
                ->where('category', 'upcoming_interview')
                ->whereDate('created_at', $today)
                ->get()
                ->contains(fn (AtsNotification $n) => ($n->data['title'] ?? '') === $title);

            if (! $alreadySent) {
                AtsNotification::sendToUser(
                    $recruiterId,
                    $title,
                    "{$interview->candidate?->full_name} at {$interview->interview_time} ({$interview->jobRequirement?->title}).",
                    'upcoming_interview',
                    '/interviews?date='.$today
                );
            }
        }
    }

    $this->info('Dispatched overdue task and interview reminders.');
})->purpose('Send daily overdue follow-up and interview reminders');

// Hostinger Shared Hosting Cron Schedule (runs via `* * * * * php artisan schedule:run`)
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::command('ats:backup --type=full')
    ->dailyAt('02:00')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping();

Schedule::command('ats:storage-check')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('ats:send-reminders')
    ->dailyAt('09:45')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping();
