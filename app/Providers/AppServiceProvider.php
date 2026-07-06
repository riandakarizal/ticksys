<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Super Admins bypass all policy checks within their own tenant.
        // Non-super-admin abilities are evaluated by the relevant Policy class.
        Gate::before(function ($user, string $ability): ?bool {
            return $user->isSuperAdmin() ? true : null;
        });
    }
}
