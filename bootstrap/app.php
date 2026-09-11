<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureSboAdviser;
use App\Http\Middleware\EnsureSboOfficer;
use App\Http\Middleware\EnsureStudent;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'sbo.adviser' => EnsureSboAdviser::class,
            'sbo.officer' => EnsureSboOfficer::class,
            'password.changed' => EnsurePasswordChanged::class,
            'student' => EnsureStudent::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
