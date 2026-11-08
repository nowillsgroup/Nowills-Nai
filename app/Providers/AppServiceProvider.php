<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('write', fn (User $user) => $user->canWrite());
        Gate::define('admin', fn (User $user) => $user->isAdmin());
    }
}
