<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.student', function ($view): void {
            $user = request()->user();

            $view->with([
                'studentNotifications' => $user
                    ? $user->notifications()->latest()->limit(10)->get()
                    : collect(),
                'studentUnreadNotificationCount' => $user
                    ? $user->unreadNotifications()->count()
                    : 0,
            ]);
        });
    }
}
