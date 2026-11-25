<?php

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CandidateController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientPortalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\JobRequirementController;
use App\Http\Controllers\OfferAndJoiningController;
use App\Http\Controllers\ProductivityAndReportController;
use App\Http\Controllers\RecruitmentOperationController;
use App\Http\Middleware\AuthorizeRecruitmentAccess;
use Illuminate\Support\Facades\Route;

// Public & Guest Authentication Routes
Route::get('/', fn () => redirect()->route('login'));
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
Route::get('/invite/{token}', [AuthController::class, 'showAcceptInvite'])->name('invite.show');
Route::post('/invite/{token}', [AuthController::class, 'acceptInvite'])->name('invite.accept');

// Authenticated Routes
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/two-factor/challenge', [AuthController::class, 'showTwoFactorChallenge'])->name('two-factor.challenge');
    Route::post('/two-factor/challenge', [AuthController::class, 'verifyTwoFactorChallenge'])->name('two-factor.verify');

    Route::get('/profile', [AuthController::class, 'profile'])->name('profile.show');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/two-factor', [AuthController::class, 'toggleTwoFactor'])->name('profile.two-factor');
    Route::post('/profile/two-factor/confirm', [AuthController::class, 'confirmTwoFactor'])->name('profile.two-factor.confirm');
    Route::post('/profile/two-factor/recovery-codes', [AuthController::class, 'regenerateRecoveryCodes'])->name('profile.two-factor.recovery-codes');
    Route::post('/profile/revoke-sessions', [AuthController::class, 'revokeOtherSessions'])->name('profile.revoke-sessions');

    // Secure Private Document Downloads (RBAC & Client-Isolation enforced in controller)
    Route::get('/documents/{storageRecord}/download', [DocumentController::class, 'download'])->name('documents.download');

    // Global Command Palette Search
    Route::get('/global-search', [DashboardController::class, 'globalSearch'])->name('global.search');

    // Notifications
    Route::get('/notifications', [ProductivityAndReportController::class, 'notifications'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [ProductivityAndReportController::class, 'markNotificationRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [ProductivityAndReportController::class, 'markAllNotificationsRead'])->name('notifications.read-all');

    // =========================================================================
    // CLIENT PORTAL ROUTES (Strictly isolated to client organisation)
    // =========================================================================
    Route::prefix('portal')->name('portal.')->middleware(['client.isolation', 'role_or_permission:client,super_admin'])->group(function () {
        Route::get('/dashboard', [ClientPortalController::class, 'dashboard'])->name('dashboard');
        Route::post('/requirements', [ClientPortalController::class, 'storeRequirement'])->name('requirements.store');
        Route::get('/compare', [ClientPortalController::class, 'compare'])->name('compare');
        Route::post('/submissions/{submission}/decision', [RecruitmentOperationController::class, 'recordClientDecision'])->name('submissions.decision');
        Route::post('/interviews/{interview}/result', [InterviewController::class, 'recordRoundResult'])->name('interviews.result');
        Route::post('/applications/{application}/confirm-selection', [ClientPortalController::class, 'confirmFinalSelection'])->name('applications.confirm-selection');
        Route::post('/joinings/{joining}/confirm', [OfferAndJoiningController::class, 'confirmJoining'])->name('joinings.confirm');
    });

    // =========================================================================
    // INTERNAL STAFF CRM & ATS WORKSPACE ROUTES
    // =========================================================================
    Route::middleware(['role_or_permission:super_admin,manager,team_leader,recruiter', AuthorizeRecruitmentAccess::class])->group(function () {
        // Workspace
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/my-work', [DashboardController::class, 'myWork'])->name('my-work');
        Route::get('/calendar', [DashboardController::class, 'calendar'])->name('calendar');
        Route::get('/tasks', [ProductivityAndReportController::class, 'tasks'])->name('tasks.index');
        Route::post('/tasks', [ProductivityAndReportController::class, 'storeTask'])->name('tasks.store');
        Route::post('/tasks/{task}/complete', [ProductivityAndReportController::class, 'completeTask'])->name('tasks.complete');

        // Clients
        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');
        Route::middleware(['role_or_permission:clients.manage,super_admin,manager'])->group(function () {
            Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
            Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
            Route::post('/clients/{client}/contacts', [ClientController::class, 'storeContact'])->name('clients.contacts.store');
        });

        // Job Requirements & Assignments
        Route::get('/jobs', [JobRequirementController::class, 'index'])->name('jobs.index');
        Route::get('/jobs/{job}', [JobRequirementController::class, 'show'])->name('jobs.show');
        Route::middleware(['role_or_permission:jobs.create,super_admin,manager,team_leader'])->group(function () {
            Route::post('/jobs', [JobRequirementController::class, 'store'])->name('jobs.store');
            Route::put('/jobs/{job}', [JobRequirementController::class, 'update'])->name('jobs.update');
            Route::post('/jobs/{job}/assign', [JobRequirementController::class, 'assignRecruiters'])->name('jobs.assign');
            Route::post('/jobs/{job}/interview-rounds', [JobRequirementController::class, 'addInterviewRoundDefinition'])->name('jobs.interview-rounds.store');
            Route::post('/jobs/{job}/duplicate', [JobRequirementController::class, 'duplicate'])->name('jobs.duplicate');
        });

        // Candidates & Duplicate Detection
        Route::get('/candidates', [CandidateController::class, 'index'])->name('candidates.index');
        Route::get('/candidates/check-duplicates', [CandidateController::class, 'checkDuplicates'])->name('candidates.check-duplicates');
        Route::get('/candidates/import-template', [CandidateController::class, 'downloadImportTemplate'])->name('candidates.import-template');
        Route::post('/candidates/import', [CandidateController::class, 'import'])->middleware('role_or_permission:candidates.import,super_admin,manager,team_leader')->name('candidates.import');
        Route::get('/candidates/export', [CandidateController::class, 'export'])->middleware('role_or_permission:candidates.export,super_admin,manager')->name('candidates.export');
        Route::post('/candidates', [CandidateController::class, 'store'])->name('candidates.store');
        Route::get('/candidates/{candidate}', [CandidateController::class, 'show'])->name('candidates.show');
        Route::get('/candidates/{candidate}/preview', [CandidateController::class, 'preview'])->name('candidates.preview');
        Route::put('/candidates/{candidate}', [CandidateController::class, 'update'])->name('candidates.update');
        Route::post('/candidates/{candidate}/resumes', [CandidateController::class, 'uploadResume'])->name('candidates.resumes.store');
        Route::post('/candidates/{candidate}/notes', [CandidateController::class, 'storeNote'])->name('candidates.notes.store');
        Route::post('/candidates/{candidate}/merge', [CandidateController::class, 'merge'])->middleware('role_or_permission:candidates.merge,super_admin,manager')->name('candidates.merge');

        // Applications, Calling, Screening, Submissions & Pipeline
        Route::get('/applications', [RecruitmentOperationController::class, 'applications'])->name('applications.index');
        Route::post('/applications', [RecruitmentOperationController::class, 'storeApplication'])->name('applications.store');
        Route::post('/candidates/{candidate}/calls', [RecruitmentOperationController::class, 'storeCallLog'])->name('candidates.calls.store');
        Route::post('/applications/{application}/screening', [RecruitmentOperationController::class, 'storeScreening'])->name('applications.screening.store');
        Route::get('/submissions', [RecruitmentOperationController::class, 'submissions'])->name('submissions.index');
        Route::post('/applications/{application}/submit', [RecruitmentOperationController::class, 'submitProfile'])->name('applications.submit');
        Route::post('/submissions/bulk', [RecruitmentOperationController::class, 'bulkSubmitProfiles'])->name('submissions.bulk');
        Route::post('/submissions/{submission}/decision', [RecruitmentOperationController::class, 'recordClientDecision'])->name('submissions.decision');
        Route::get('/pipeline', [RecruitmentOperationController::class, 'pipeline'])->name('pipeline.index');
        Route::post('/applications/{application}/transition', [RecruitmentOperationController::class, 'transitionStage'])->name('applications.transition');

        // Interviews & Unlimited Rounds
        Route::get('/interviews', [InterviewController::class, 'index'])->name('interviews.index');
        Route::post('/applications/{application}/interviews', [InterviewController::class, 'scheduleRound'])->name('applications.interviews.schedule');
        Route::post('/interviews/{interview}/result', [InterviewController::class, 'recordRoundResult'])->name('interviews.result');
        Route::post('/interviews/{interview}/override', [InterviewController::class, 'overrideRound'])->middleware('role_or_permission:interviews.override,super_admin,manager')->name('interviews.override');

        // Offers, Joinings, Placements & Replacements
        Route::get('/offers', [OfferAndJoiningController::class, 'offers'])->name('offers.index');
        Route::post('/applications/{application}/offer', [OfferAndJoiningController::class, 'storeOrUpdateOffer'])->name('applications.offer.store');
        Route::get('/joinings', [OfferAndJoiningController::class, 'joinings'])->name('joinings.index');
        Route::post('/joinings/{joining}/confirm', [OfferAndJoiningController::class, 'confirmJoining'])->name('joinings.confirm');
        Route::post('/joinings/{joining}/did-not-join', [OfferAndJoiningController::class, 'markDidNotJoin'])->name('joinings.did-not-join');
        Route::post('/joinings/{joining}/checkin', [OfferAndJoiningController::class, 'recordCheckin'])->name('joinings.checkin');
        Route::post('/joinings/{joining}/replacement', [OfferAndJoiningController::class, 'triggerReplacement'])->middleware('role_or_permission:replacements.reopen,super_admin,manager')->name('joinings.replacement');

        // Management & Reports
        Route::get('/management/recruiter-performance', [DashboardController::class, 'recruiterPerformance'])->name('management.recruiter-performance');
        Route::get('/management/client-performance', [DashboardController::class, 'clientPerformance'])->name('management.client-performance');
        Route::get('/reports', [ProductivityAndReportController::class, 'reports'])->name('reports.index');
        Route::get('/reports/export', [ProductivityAndReportController::class, 'exportReport'])->name('reports.export');
    });

    // =========================================================================
    // ADMINISTRATION & SYSTEM HEALTH ROUTES
    // =========================================================================
    Route::prefix('admin')->name('admin.')->group(function () {
        // Team Management accessible to Super Admin, Manager & Team Leader
        Route::middleware(['role_or_permission:teams.manage,super_admin,manager,team_leader'])->group(function () {
            Route::get('/teams', [AdministrationController::class, 'teams'])->name('teams');
            Route::post('/teams', [AdministrationController::class, 'storeTeam'])->name('teams.store');
        });

        // Super Admin Only Modules
        Route::middleware(['role_or_permission:super_admin'])->group(function () {
            Route::get('/users', [AdministrationController::class, 'users'])->name('users');
            Route::post('/users', [AdministrationController::class, 'storeUser'])->name('users.store');
            Route::put('/users/{user}', [AdministrationController::class, 'updateUser'])->name('users.update');
            Route::post('/users/{user}/toggle-status', [AdministrationController::class, 'toggleUserStatus'])->name('users.toggle-status');

            Route::get('/roles', [AdministrationController::class, 'roles'])->name('roles');
            Route::put('/roles/{role}/permissions', [AdministrationController::class, 'updateRolePermissions'])->name('roles.permissions.update');

            Route::get('/settings', [AdministrationController::class, 'settings'])->name('settings');
            Route::put('/settings', [AdministrationController::class, 'updateSettings'])->name('settings.update');

            Route::get('/email-templates', [AdministrationController::class, 'emailTemplates'])->name('email-templates');
            Route::put('/email-templates/{emailTemplate}', [AdministrationController::class, 'updateEmailTemplate'])->name('email-templates.update');

            Route::get('/custom-fields', [AdministrationController::class, 'customFields'])->name('custom-fields');
            Route::post('/custom-fields', [AdministrationController::class, 'storeCustomField'])->name('custom-fields.store');

            Route::get('/audit-logs', [AdministrationController::class, 'auditLogs'])->name('audit-logs');
            Route::get('/storage', [AdministrationController::class, 'storage'])->name('storage');

            Route::get('/backups', [AdministrationController::class, 'backups'])->name('backups');
            Route::post('/backups/run', [AdministrationController::class, 'runBackup'])->name('backups.run');
            Route::post('/backups/{backupLog}/restore', [AdministrationController::class, 'restoreBackup'])->name('backups.restore');

            Route::get('/recovery', [AdministrationController::class, 'recovery'])->name('recovery');
            Route::post('/recovery/restore', [AdministrationController::class, 'restoreDeletedRecord'])->name('recovery.restore');

            Route::get('/system-health', [AdministrationController::class, 'systemHealth'])->name('system-health');
        });
    });
});
