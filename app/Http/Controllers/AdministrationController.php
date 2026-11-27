<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BackupLog;
use App\Models\Candidate;
use App\Models\Client;
use App\Models\CustomField;
use App\Models\EmailTemplate;
use App\Models\JobRequirement;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StorageRecord;
use App\Models\SystemSetting;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditService;
use App\Services\BackupService;
use App\Services\CodeGeneratorService;
use App\Services\StorageManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdministrationController extends Controller
{
    public function __construct(
        private readonly StorageManagementService $storageService,
        private readonly BackupService $backupService
    ) {}

    public function users(Request $request)
    {
        $query = User::with(['role', 'team', 'client']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%");
            });
        }
        if ($roleId = $request->query('role_id')) {
            $query->where('role_id', $roleId);
        }

        $users = $query->orderBy('id')->paginate(25)->withQueryString();
        $roles = Role::orderBy('id')->get();
        $teams = Team::orderBy('name')->get();
        $clients = Client::orderBy('company_name')->get();

        return view('admin.users', compact('users', 'roles', 'teams', 'clients'));
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'designation' => ['nullable', 'string', 'max:100'],
            'role_id' => ['required', 'exists:roles,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'password' => ['nullable', 'string', 'min:8'],
            'daily_sourcing_target' => ['nullable', 'integer', 'min:0'],
            'daily_call_target' => ['nullable', 'integer', 'min:0'],
            'daily_submission_target' => ['nullable', 'integer', 'min:0'],
            'send_invitation' => ['nullable', 'boolean'],
        ]);

        $role = Role::findOrFail($data['role_id']);
        if ($role->slug === 'client' && empty($data['client_id'])) {
            return back()->withInput()->withErrors([
                'client_id' => 'Please select a Client Organisation when creating a Client HR portal account.',
            ]);
        }

        $org = Organisation::first();
        $inviteToken = $request->boolean('send_invitation') ? Str::random(40) : null;
        $rawPassword = $data['password'] ?: Str::random(16);
        $empCode = CodeGeneratorService::next(User::class, 'employee_code', 'ORB-USR-', 3);

        $user = User::create([
            'employee_code' => $empCode,
            'organisation_id' => $org?->id,
            'role_id' => $role->id,
            'team_id' => $role->slug === 'client' ? null : ($data['team_id'] ?? null),
            'client_id' => $role->slug === 'client' ? ($data['client_id'] ?? null) : null,
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            'designation' => $data['designation'] ?? null,
            'password' => Hash::make($rawPassword),
            'email_verified_at' => $inviteToken ? null : now(),
            'is_active' => true,
            'invited_by' => Auth::id(),
            'invitation_token' => $inviteToken,
            'daily_sourcing_target' => $data['daily_sourcing_target'] ?? 10,
            'daily_call_target' => $data['daily_call_target'] ?? 35,
            'daily_submission_target' => $data['daily_submission_target'] ?? 5,
        ]);

        if ($role->slug !== 'client' && ! empty($data['team_id'])) {
            $user->teams()->syncWithoutDetaching([
                $data['team_id'] => ['team_role' => $user->roleSlug(), 'is_active' => true],
            ]);
        }

        AuditService::log('user.created', $user, $user->id, null, [
            'email' => $user->email,
            'role_id' => $user->role_id,
            'team_id' => $user->team_id,
        ]);

        if ($inviteToken) {
            $request->session()->flash('invitation_link_once', route('invite.show', $inviteToken));
        }

        return back()->with('success', "Account created for {$user->name} ({$user->employee_code}).");
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'designation' => ['nullable', 'string', 'max:100'],
            'role_id' => ['required', 'exists:roles,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'password' => ['nullable', 'string', 'min:8'],
            'daily_sourcing_target' => ['nullable', 'integer', 'min:0'],
            'daily_call_target' => ['nullable', 'integer', 'min:0'],
            'daily_submission_target' => ['nullable', 'integer', 'min:0'],
        ]);

        $role = Role::findOrFail($data['role_id']);
        if ($role->slug === 'client' && empty($data['client_id'])) {
            return back()->withInput()->withErrors([
                'client_id' => 'Please select a Client Organisation when assigning the Client HR role.',
            ]);
        }

        $oldValues = $user->only(['role_id', 'team_id', 'client_id', 'designation']);

        $payload = [
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'designation' => $data['designation'] ?? null,
            'role_id' => $role->id,
            'team_id' => $role->slug === 'client' ? null : ($data['team_id'] ?? null),
            'client_id' => $role->slug === 'client' ? ($data['client_id'] ?? null) : null,
            'daily_sourcing_target' => $data['daily_sourcing_target'] ?? $user->daily_sourcing_target,
            'daily_call_target' => $data['daily_call_target'] ?? $user->daily_call_target,
            'daily_submission_target' => $data['daily_submission_target'] ?? $user->daily_submission_target,
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
            $payload['failed_login_attempts'] = 0;
            $payload['locked_until'] = null;
        }

        $user->update($payload);

        if ($role->slug !== 'client' && ! empty($data['team_id'])) {
            $user->teams()->syncWithoutDetaching([
                $data['team_id'] => ['team_role' => $role->slug, 'is_active' => true],
            ]);
        }

        AuditService::log('user.updated', $user, $user->id, $oldValues, collect($payload)->except('password')->all());

        return back()->with('success', "Updated user account {$user->name}.");
    }

    public function toggleUserStatus(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->withErrors(['user' => 'You cannot deactivate your own active Super Admin session.']);
        }

        $user->update([
            'is_active' => ! $user->is_active,
            'locked_until' => null,
            'failed_login_attempts' => 0,
        ]);

        if (! $user->is_active) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        AuditService::log(
            $user->is_active ? 'user.activated' : 'user.deactivated',
            $user,
            $user->id,
            null,
            ['is_active' => $user->is_active]
        );

        return back()->with('success', "User {$user->name} is now ".($user->is_active ? 'Active' : 'Deactivated').'.');
    }

    public function roles()
    {
        $roles = Role::with('permissions')->orderBy('id')->get();
        $permissions = Permission::orderBy('module')->orderBy('slug')->get()->groupBy('module');

        return view('admin.roles', compact('roles', 'permissions'));
    }

    public function updateRolePermissions(Request $request, Role $role)
    {
        if ($role->slug === 'super_admin') {
            return back()->withErrors(['role' => 'Super Admin retains all system permissions by design.']);
        }

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $oldPerms = $role->permissions()->pluck('slug')->all();
        $role->permissions()->sync($data['permissions'] ?? []);
        $newPerms = $role->permissions()->pluck('slug')->all();

        AuditService::log('role.permissions_updated', $role, $role->id, ['permissions' => $oldPerms], ['permissions' => $newPerms]);

        return back()->with('success', "Permissions updated for {$role->name}.");
    }

    public function teams()
    {
        $teams = Team::with(['manager', 'teamLeader', 'members'])->withCount('jobRequirements')->get();
        $managers = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['super_admin', 'manager']))->get();
        $teamLeaders = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['team_leader', 'manager']))->get();
        $recruiters = User::whereHas('role', fn ($q) => $q->whereIn('slug', ['recruiter', 'team_leader']))->get();

        return view('admin.teams', compact('teams', 'managers', 'teamLeaders', 'recruiters'));
    }

    public function storeTeam(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'specialization' => ['nullable', 'string', 'max:120'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'team_leader_id' => ['nullable', 'exists:users,id'],
            'daily_sourcing_target' => ['required', 'integer', 'min:1'],
            'daily_call_target' => ['required', 'integer', 'min:1'],
            'daily_submission_target' => ['required', 'integer', 'min:1'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:users,id'],
        ]);

        $team = Team::create([
            'code' => CodeGeneratorService::next(Team::class, 'code', 'TM-', 3),
            'name' => $data['name'],
            'specialization' => $data['specialization'] ?? null,
            'manager_id' => $data['manager_id'] ?? null,
            'team_leader_id' => $data['team_leader_id'] ?? null,
            'daily_sourcing_target' => $data['daily_sourcing_target'],
            'daily_call_target' => $data['daily_call_target'],
            'daily_submission_target' => $data['daily_submission_target'],
            'is_active' => true,
        ]);

        if (! empty($data['member_ids'])) {
            foreach ($data['member_ids'] as $uid) {
                $team->members()->syncWithoutDetaching([$uid => ['team_role' => 'recruiter', 'is_active' => true]]);
                User::where('id', $uid)->update(['team_id' => $team->id]);
            }
        }

        AuditService::log('team.created', $team, $team->id, null, $team->toArray());

        return back()->with('success', "Recruitment team {$team->name} ({$team->code}) created.");
    }

    public function settings()
    {
        $organisation = Organisation::first();
        $settings = SystemSetting::orderBy('group')->orderBy('id')->get()->groupBy('group');

        return view('admin.settings', compact('organisation', 'settings'));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:180'],
            'legal_name' => ['required', 'string', 'max:180'],
            'default_timezone' => ['required', 'string', 'max:64'],
            'default_currency' => ['required', 'string', 'max:10'],
            'work_start_time' => ['required', 'string', 'max:10'],
            'work_end_time' => ['required', 'string', 'max:10'],
            'default_replacement_days' => ['required', 'integer', 'min:0', 'max:365'],
            'dpdp_retention_months' => ['required', 'integer', 'min:1', 'max:120'],
            'candidate_sources' => ['nullable', 'string'],
            'rejection_reasons' => ['nullable', 'string'],
        ]);

        $org = Organisation::first();
        if ($org) {
            $org->update([
                'name' => $data['company_name'],
                'legal_name' => $data['legal_name'],
                'default_timezone' => $data['default_timezone'],
                'default_currency' => $data['default_currency'],
                'work_start_time' => $data['work_start_time'],
                'work_end_time' => $data['work_end_time'],
                'default_replacement_days' => $data['default_replacement_days'],
            ]);
        }

        SystemSetting::setValue('company_name', $data['company_name'], 'company', 'string', Auth::id());
        SystemSetting::setValue('legal_company_name', $data['legal_name'], 'company', 'string', Auth::id());
        SystemSetting::setValue('default_timezone', $data['default_timezone'], 'company', 'string', Auth::id());
        SystemSetting::setValue('default_currency', $data['default_currency'], 'company', 'string', Auth::id());
        SystemSetting::setValue('work_hours_start', $data['work_start_time'], 'schedule', 'string', Auth::id());
        SystemSetting::setValue('work_hours_end', $data['work_end_time'], 'schedule', 'string', Auth::id());
        SystemSetting::setValue('default_replacement_days', $data['default_replacement_days'], 'recruitment', 'integer', Auth::id());
        SystemSetting::setValue('dpdp_retention_months', $data['dpdp_retention_months'], 'privacy', 'integer', Auth::id());

        if (! empty($data['candidate_sources'])) {
            $sources = array_values(array_filter(array_map('trim', explode(',', $data['candidate_sources']))));
            SystemSetting::setValue('candidate_sources', $sources, 'recruitment', 'json', Auth::id());
        }

        if (! empty($data['rejection_reasons'])) {
            $reasons = array_values(array_filter(array_map('trim', explode(',', $data['rejection_reasons']))));
            SystemSetting::setValue('rejection_reasons', $reasons, 'recruitment', 'json', Auth::id());
        }

        AuditService::log('settings.updated', 'SystemSetting', null, null, $data);

        return back()->with('success', 'Business and recruitment settings updated.');
    }

    public function emailTemplates()
    {
        $templates = EmailTemplate::orderBy('id')->get();

        return view('admin.email-templates', compact('templates'));
    }

    public function updateEmailTemplate(Request $request, EmailTemplate $emailTemplate)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $emailTemplate->update([
            'subject' => $data['subject'],
            'body_html' => $data['body_html'],
            'is_active' => $request->boolean('is_active', true),
            'updated_by' => Auth::id(),
        ]);

        AuditService::log('email_template.updated', $emailTemplate, $emailTemplate->id);

        return back()->with('success', "Email template [{$emailTemplate->name}] updated.");
    }

    public function customFields()
    {
        $customFields = CustomField::orderBy('entity_type')->orderBy('sort_order')->get();

        return view('admin.custom-fields', compact('customFields'));
    }

    public function storeCustomField(Request $request)
    {
        $data = $request->validate([
            'entity_type' => ['required', 'in:candidate,job_requirement,client'],
            'field_label' => ['required', 'string', 'max:150'],
            'field_type' => ['required', 'in:text,number,date,select,boolean'],
            'options_csv' => ['nullable', 'string'],
            'is_required' => ['nullable', 'boolean'],
        ]);

        $fieldKey = Str::snake($data['field_label']);
        $options = ! empty($data['options_csv'])
            ? array_values(array_filter(array_map('trim', explode(',', $data['options_csv']))))
            : null;

        $field = CustomField::updateOrCreate(
            ['entity_type' => $data['entity_type'], 'field_key' => $fieldKey],
            [
                'field_label' => $data['field_label'],
                'field_type' => $data['field_type'],
                'options' => $options,
                'is_required' => $request->boolean('is_required'),
                'is_active' => true,
            ]
        );

        AuditService::log('custom_field.saved', $field, $field->id, null, $field->toArray());

        return back()->with('success', "Custom field [{$field->field_label}] saved.");
    }

    public function auditLogs(Request $request)
    {
        $query = AuditLog::query()->orderByDesc('created_at');

        if ($action = $request->query('action')) {
            $query->where('action', 'like', "%{$action}%");
        }
        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        $logs = $query->paginate(50)->withQueryString();
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('admin.audit-logs', compact('logs', 'users'));
    }

    public function storage()
    {
        $metrics = $this->storageService->getStorageMetrics();
        $recentFiles = StorageRecord::with('uploader')->latest()->paginate(25);

        return view('admin.storage', compact('metrics', 'recentFiles'));
    }

    public function backups()
    {
        $backups = BackupLog::with('triggerer')->latest()->paginate(20);
        $externalConfigured = $this->backupService->isExternalDestinationConfigured();

        return view('admin.backups', compact('backups', 'externalConfigured'));
    }

    public function runBackup(Request $request)
    {
        $log = $this->backupService->createEncryptedBackup($request->input('backup_type', 'full'));

        return back()->with('success', "Encrypted backup [{$log->backup_code}] created and verified (SHA-256: ".substr($log->sha256_checksum, 0, 16).'...).');
    }

    public function restoreBackup(BackupLog $backupLog)
    {
        $summary = $this->backupService->verifyAndRestoreBackup($backupLog);

        return back()->with(
            'success',
            "Backup [{$summary['backup_code']}] verified and restored ({$summary['restored_records']} records, {$summary['restored_files']} files recovered)."
        );
    }

    public function recovery()
    {
        $deletedCandidates = Candidate::onlyTrashed()->latest('deleted_at')->get();
        $deletedJobs = JobRequirement::onlyTrashed()->with('client')->latest('deleted_at')->get();
        $deletedClients = Client::onlyTrashed()->latest('deleted_at')->get();

        return view('admin.recovery', compact('deletedCandidates', 'deletedJobs', 'deletedClients'));
    }

    public function restoreDeletedRecord(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:candidate,job,client'],
            'id' => ['required', 'integer'],
        ]);

        $model = match ($data['type']) {
            'candidate' => Candidate::onlyTrashed()->findOrFail($data['id']),
            'job' => JobRequirement::onlyTrashed()->findOrFail($data['id']),
            'client' => Client::onlyTrashed()->findOrFail($data['id']),
        };

        $model->restore();
        AuditService::log('record.restored', $model, $model->id, ['deleted_at' => 'trashed'], ['deleted_at' => null]);

        return back()->with('success', ucfirst($data['type']).' record restored successfully.');
    }

    public function systemHealth()
    {
        $dbConnected = false;
        try {
            DB::connection()->getPdo();
            $dbConnected = true;
        } catch (\Throwable) {
            $dbConnected = false;
        }

        $storageMetrics = $this->storageService->getStorageMetrics();
        $lastBackup = BackupLog::where('status', 'completed')->orWhere('status', 'restored')->latest('completed_at')->first();
        $failedBackupsCount = BackupLog::where('status', 'failed')->count();
        $failedJobsCount = DB::table('failed_jobs')->count();
        $pendingQueueCount = DB::table('jobs')->count();
        $externalBackupReady = $this->backupService->isExternalDestinationConfigured();

        $health = [
            'app_version' => '1.1.0-enterprise',
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'timezone' => config('app.timezone'),
            'db_connected' => $dbConnected,
            'db_driver' => DB::connection()->getDriverName(),
            'storage_metrics' => $storageMetrics,
            'last_backup' => $lastBackup,
            'failed_backups_count' => $failedBackupsCount,
            'failed_jobs_count' => $failedJobsCount,
            'pending_queue_count' => $pendingQueueCount,
            'mail_mailer' => config('mail.default'),
            'external_backup_ready' => $externalBackupReady,
        ];

        return view('admin.system-health', compact('health'));
    }
}
