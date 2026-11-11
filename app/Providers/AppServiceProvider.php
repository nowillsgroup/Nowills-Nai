<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(\App\Services\Workforce\Planner::class, \App\Services\Workforce\OpenAiPlanner::class);
    }

    public function boot(): void
    {
        Gate::define('write', fn (User $user) => $user->canWrite());
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // The Arena preview panel sits behind a TLS-terminating proxy. The
        // browser-facing origin is APP_URL, while PHP may only see an internal
        // HTTP host. Pin generated links/redirects to the public origin in the
        // local preview so forms and navigation never point at localhost/HTTP.
        if ($this->app->environment('local') && config('app.url')) {
            URL::forceRootUrl(config('app.url'));
            $scheme = parse_url(config('app.url'), PHP_URL_SCHEME);
            if (is_string($scheme) && $scheme !== '') {
                URL::forceScheme($scheme);
            }
        }
    }
}
