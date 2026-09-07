<?php

use App\Http\Controllers\Adviser\AttendanceManagementController;
use App\Http\Controllers\Adviser\EventAssignmentController;
use App\Http\Controllers\Adviser\EventManagementController;
use App\Http\Controllers\Adviser\TeamManagementController;
use App\Http\Controllers\Adviser\UserManagementController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('active')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::prefix('adviser')->name('adviser.')->middleware('sbo.adviser')->group(function () {
            Route::post('/events/conflicts', [EventManagementController::class, 'conflicts'])->name('events.conflicts');
            Route::resource('events', EventManagementController::class)->except('destroy');
            Route::delete('/events/{event}', [EventManagementController::class, 'destroy'])->name('events.destroy');
            Route::post('/events/{event}/assignments', [EventAssignmentController::class, 'storeForEvent'])->name('events.assignments.store');
            Route::post('/events/{event}/restore', [EventManagementController::class, 'restore'])->name('events.restore');
            Route::delete('/events/{event}/force', [EventManagementController::class, 'forceDelete'])->name('events.force-delete');
            Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
            Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
            Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
            Route::patch('/users/{user}/status', [UserManagementController::class, 'toggleStatus'])->name('users.status');
            Route::post('/users/{user}/events', [EventAssignmentController::class, 'store'])->name('users.events.store');
            Route::delete('/users/{user}/events/{event}', [EventAssignmentController::class, 'destroy'])->name('users.events.destroy');
            Route::post('/users/{user}/events/{event}/restore', [EventAssignmentController::class, 'restore'])->name('users.events.restore');
            Route::resource('teams', TeamManagementController::class)->except(['show', 'destroy']);
            Route::patch('/teams/{team}/status', [TeamManagementController::class, 'toggleStatus'])->name('teams.status');
            Route::get('/attendance', [AttendanceManagementController::class, 'index'])->name('attendance.index');
            Route::get('/attendance/{event}', [AttendanceManagementController::class, 'show'])->name('attendance.show');
            Route::put('/attendance/{event}', [AttendanceManagementController::class, 'update'])->name('attendance.update');
        });
    });
});
