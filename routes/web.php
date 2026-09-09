<?php

use App\Http\Controllers\Adviser\AttendanceManagementController;
use App\Http\Controllers\Adviser\EventAssignmentController;
use App\Http\Controllers\Adviser\EventManagementController;
use App\Http\Controllers\Adviser\OfficerManagementController;
use App\Http\Controllers\Adviser\ScoreManagementController;
use App\Http\Controllers\Adviser\TeamManagementController;
use App\Http\Controllers\Adviser\UserManagementController;
use App\Http\Controllers\Auth\AccountPasswordResetController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Officer\AttendanceController as OfficerAttendanceController;
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

        Route::prefix('officer')->name('officer.')->middleware('sbo.officer')->group(function () {
            Route::get('/attendance/{event?}', [OfficerAttendanceController::class, 'index'])->name('attendance.index');
            Route::post('/attendance/{event}/scan', [OfficerAttendanceController::class, 'scan'])->name('attendance.scan');
        });

        Route::prefix('adviser')->name('adviser.')->middleware('sbo.adviser')->group(function () {
            Route::post('/events/conflicts', [EventManagementController::class, 'conflicts'])->name('events.conflicts');
            Route::resource('events', EventManagementController::class)->except('destroy');
            Route::patch('/events/{event}/status', [EventManagementController::class, 'updateStatus'])->name('events.status');
            Route::delete('/events/{event}', [EventManagementController::class, 'destroy'])->name('events.destroy');
            Route::post('/events/{event}/assignments', [EventAssignmentController::class, 'storeForEvent'])->name('events.assignments.store');
            Route::post('/events/{event}/restore', [EventManagementController::class, 'restore'])->name('events.restore');
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
        });
    });
});
