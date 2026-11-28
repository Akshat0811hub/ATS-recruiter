<?php

namespace App\Providers;

use App\Models\AtsNotification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
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
        if ($this->app->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        View::composer('layouts.app', function ($view): void {
            $view->with('unreadNotificationsCount', Auth::check()
                ? AtsNotification::where('notifiable_type', User::class)
                    ->where('notifiable_id', Auth::id())->whereNull('read_at')->count()
                : 0);
        });
    }
}
