<?php

use App\Http\Controllers\Adviser\AttendanceManagementController;
use App\Http\Controllers\Adviser\AnnouncementController;
use App\Http\Controllers\Adviser\EventAssignmentController;
use App\Http\Controllers\Adviser\EventManagementController;
use App\Http\Controllers\Adviser\LeaderboardController as AdviserLeaderboardController;
use App\Http\Controllers\Adviser\OfficerManagementController;
use App\Http\Controllers\Adviser\PostReviewController;
use App\Http\Controllers\Adviser\ScoreManagementController;
use App\Http\Controllers\Adviser\TeamManagementController;
use App\Http\Controllers\Adviser\UserManagementController;
use App\Http\Controllers\Auth\AccountPasswordResetController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventFeatureController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Officer\AttendanceController as OfficerAttendanceController;
use App\Http\Controllers\Student\AttendanceController as StudentAttendanceController;
use App\Http\Controllers\Student\EventController as StudentEventController;
use App\Http\Controllers\Student\HomeController as StudentHomeController;
use App\Http\Controllers\Student\LeaderboardController;
use App\Http\Controllers\Student\NotificationController as StudentNotificationController;
use App\Http\Controllers\Student\PostCommentController;
use App\Http\Controllers\Student\PostController;
use App\Http\Controllers\Student\PostReactionController;
use App\Http\Controllers\Student\ProfileController;
use App\Http\Controllers\Student\TeamController as StudentTeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:6,1');
    Route::get('/forgot-password', [AccountPasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [AccountPasswordResetController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{user}/{token}', [AccountPasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [AccountPasswordResetController::class, 'update'])->name('password.reset.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/password/change', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('/password/change', [PasswordChangeController::class, 'update'])->name('password.update');

    Route::middleware(['active', 'password.changed'])->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::prefix('student')->name('student.')->middleware('student')->group(function () {
            Route::get('/home', StudentHomeController::class)->name('home');
            Route::get('/events', [StudentEventController::class, 'index'])->name('events.index');
            Route::get('/attendance', [StudentAttendanceController::class, 'show'])->name('attendance.show');
            Route::get('/team', [StudentTeamController::class, 'show'])->name('team.show');
            Route::get('/rankings', [LeaderboardController::class, 'index'])->name('leaderboard.index');
            Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
            Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
            Route::get('/settings', [ProfileController::class, 'settings'])->name('settings');
            Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::patch('/notifications/read', [StudentNotificationController::class, 'markAllAsRead'])->name('notifications.read');
            Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
            Route::put('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
            Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
            Route::put('/posts/{post}/reaction', [PostReactionController::class, 'update'])->name('posts.reactions.update');
            Route::post('/posts/{post}/comments', [PostCommentController::class, 'store'])->name('posts.comments.store');
            Route::delete('/post-comments/{comment}', [PostCommentController::class, 'destroy'])->name('posts.comments.destroy');
        });

        Route::prefix('officer')->name('officer.')->middleware('sbo.officer')->group(function () {
            Route::get('/attendance/{event?}', [OfficerAttendanceController::class, 'index'])->name('attendance.index');
            Route::post('/attendance/{event}/scan', [OfficerAttendanceController::class, 'scan'])->name('attendance.scan');
            Route::patch('/events/{event}/feature', [EventFeatureController::class, 'update'])->name('events.feature');
        });

        Route::prefix('adviser')->name('adviser.')->middleware('sbo.adviser')->group(function () {
            Route::post('/events/conflicts', [EventManagementController::class, 'conflicts'])->name('events.conflicts');
            Route::resource('events', EventManagementController::class)->except('destroy');
            Route::patch('/events/{event}/status', [EventManagementController::class, 'updateStatus'])->name('events.status');
            Route::delete('/events/{event}', [EventManagementController::class, 'destroy'])->name('events.destroy');
            Route::post('/events/{event}/assignments', [EventAssignmentController::class, 'storeForEvent'])->name('events.assignments.store');
            Route::post('/events/{event}/restore', [EventManagementController::class, 'restore'])->name('events.restore');
            Route::patch('/events/{event}/feature', [EventFeatureController::class, 'update'])->name('events.feature');
            Route::delete('/events/{event}/force', [EventManagementController::class, 'forceDelete'])->name('events.force-delete');
            Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
            Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
            Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
            Route::patch('/users/{user}/status', [UserManagementController::class, 'toggleStatus'])->name('users.status');
            Route::get('/officers', [OfficerManagementController::class, 'index'])->name('officers.index');
            Route::post('/officers', [OfficerManagementController::class, 'store'])->name('officers.store');
            Route::patch('/officers/{assignment}/unassign', [OfficerManagementController::class, 'unassign'])->name('officers.unassign');
            Route::patch('/officers/unassign-selected', [OfficerManagementController::class, 'batchUnassign'])->name('officers.batch-unassign');
            Route::post('/users/{user}/events', [EventAssignmentController::class, 'store'])->name('users.events.store');
            Route::delete('/users/{user}/events/{event}', [EventAssignmentController::class, 'destroy'])->name('users.events.destroy');
            Route::post('/users/{user}/events/{event}/restore', [EventAssignmentController::class, 'restore'])->name('users.events.restore');
            Route::resource('teams', TeamManagementController::class)->except(['show', 'destroy']);
            Route::post('/teams/randomize', [TeamManagementController::class, 'randomize'])->name('teams.randomize');
            Route::patch('/teams/{team}/status', [TeamManagementController::class, 'toggleStatus'])->name('teams.status');
            Route::get('/attendance', [AttendanceManagementController::class, 'index'])->name('attendance.index');
            Route::get('/attendance/{event}', [AttendanceManagementController::class, 'show'])->name('attendance.show');
            Route::put('/attendance/{event}', [AttendanceManagementController::class, 'update'])->name('attendance.update');
            Route::get('/scores', [ScoreManagementController::class, 'index'])->name('scores.index');
            Route::get('/scores/{event}', [ScoreManagementController::class, 'show'])->name('scores.show');
            Route::put('/scores/{event}', [ScoreManagementController::class, 'update'])->name('scores.update');
            Route::post('/scores/{event}/categories', [ScoreManagementController::class, 'storeCategory'])->name('scores.categories.store');
            Route::put('/scores/{event}/categories/{scoreCategory}', [ScoreManagementController::class, 'updateCategory'])->name('scores.categories.update');
            Route::delete('/scores/{event}/categories/{scoreCategory}', [ScoreManagementController::class, 'destroyCategory'])->name('scores.categories.destroy');
            Route::get('/leaderboard', AdviserLeaderboardController::class)->name('leaderboard.index');
            Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
            Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
            Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
            Route::patch('/announcements/{announcement}/status', [AnnouncementController::class, 'updateStatus'])->name('announcements.status');
            Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
            Route::post('/announcements/{announcement}/restore', [AnnouncementController::class, 'restore'])->name('announcements.restore');
            Route::get('/posts', [PostReviewController::class, 'index'])->name('posts.index');
            Route::patch('/posts/{post}/review', [PostReviewController::class, 'update'])->name('posts.review');
        });
    });
});
