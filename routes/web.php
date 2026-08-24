<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CompetitionSessionController;
use App\Http\Controllers\Admin\JudgeAssignmentController;
use App\Http\Controllers\Admin\JudgingNudgeController;
use App\Http\Controllers\Admin\ProjectApprovalController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\Round2Controller;
use App\Http\Controllers\Admin\RubricController;
use App\Http\Controllers\Admin\SystemSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Judge\JudgeController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Participant\ProjectController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', WelcomeController::class)->name('home');

// Public Verification & Live Leaderboard
Route::get('verify/certificate/{hash}', [CertificateController::class, 'verify'])->name('certificates.verify');
Route::get('leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard.index');

Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('leaderboard', [LeaderboardController::class, 'index'])->name('dashboard.leaderboard');
    Route::inertia('profile', 'account/profile')->name('account.profile');
    Route::get('changelog', [ChangelogController::class, 'index'])->name('changelog');
    Route::post('changelog/clear-cache', [ChangelogController::class, 'clearCache'])->name('changelog.clear-cache');

    // Participant Project Routes
    Route::resource('projects', ProjectController::class)->except(['create', 'show', 'edit']);
    Route::post('projects/{project}/submit', [ProjectController::class, 'submit'])->name('projects.submit');

    // Certificate Downloads
    Route::get('projects/{project}/certificate/participation', [CertificateController::class, 'downloadParticipation'])->name('certificates.downloadParticipation');
    Route::get('projects/{project}/certificate/achievement', [CertificateController::class, 'downloadAchievement'])->name('certificates.downloadAchievement');

    // Notifications
    Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
});

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('dashboard')
    ->name('admin.')
    ->group(function () {
        Route::resource('roles', RoleController::class)->except(['create', 'show', 'edit']);
        Route::resource('users', UserController::class)->except(['create', 'show', 'edit']);
        Route::resource('sessions', CompetitionSessionController::class)->except(['create', 'show', 'edit']);
        Route::resource('categories', CategoryController::class)->except(['create', 'show', 'edit']);
        Route::resource('rubrics', RubricController::class)->except(['create', 'show', 'edit']);

        // Manual Announcements
        Route::get('announcements', [NotificationController::class, 'createAnnouncement'])->name('announcements.index');
        Route::post('announcements', [NotificationController::class, 'sendAnnouncement'])->name('announcements.send');

        // System Settings
        Route::get('admin-settings', [SystemSettingsController::class, 'index'])->name('settings.index');
        Route::post('admin-settings', [SystemSettingsController::class, 'update'])->name('settings.update');
    });

Route::middleware(['auth', 'verified', 'role:admin,lecturer'])
    ->prefix('dashboard')
    ->name('admin.')
    ->group(function () {
        // Project Approvals
        Route::get('approvals', [ProjectApprovalController::class, 'index'])->name('approvals.index');
        Route::post('approvals/approve', [ProjectApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('approvals/store', [ProjectApprovalController::class, 'storeProject'])->name('approvals.storeProject');
        Route::post('approvals/{project}/reject', [ProjectApprovalController::class, 'reject'])->name('approvals.reject');
        Route::post('approvals/cancel', [ProjectApprovalController::class, 'cancel'])->name('approvals.cancel');
        Route::put('approvals/{project}/code', [ProjectApprovalController::class, 'updateCode'])->name('approvals.code');
    });

Route::middleware(['auth', 'verified', 'role:judge'])
    ->prefix('dashboard/judge')
    ->name('judge.')
    ->group(function () {
        Route::get('evaluations', [JudgeController::class, 'index'])->name('evaluations.index');
        Route::get('evaluations/{project}', [JudgeController::class, 'show'])->name('evaluations.show');
        Route::post('evaluations/{project}', [JudgeController::class, 'store'])->name('evaluations.store');
    });

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('dashboard')
    ->name('admin.')
    ->group(function () {
        // Judge Assignments
        Route::get('assignments', [JudgeAssignmentController::class, 'index'])->name('assignments.index');
        Route::post('assignments', [JudgeAssignmentController::class, 'store'])->name('assignments.store');
        Route::delete('assignments/{assignment}', [JudgeAssignmentController::class, 'destroy'])->name('assignments.destroy');

        // Round 2 Shortlisting & Scoring
        Route::get('round2', [Round2Controller::class, 'index'])->name('round2.index');
        Route::post('round2/lock', [Round2Controller::class, 'lockToggle'])->name('round2.lockToggle');
        Route::post('round2/{project}/award', [Round2Controller::class, 'assignAward'])->name('round2.assignAward');

        // System Audit Logs
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

        // Judge Reminders (Nudge)
        Route::post('judges/nudge', [JudgingNudgeController::class, 'nudge'])->name('judges.nudge');
    });

require __DIR__.'/settings.php';
