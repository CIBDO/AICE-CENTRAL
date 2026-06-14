<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        app()->setLocale(config('app.locale', 'fr'));

        Password::defaults(fn () => Password::min(8)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols());

        Gate::before(function (?User $user, string $ability) {
            if ($user === null) {
                return null;
            }

            if ($user->hasRole('Administrateur')) {
                return true;
            }

            return $user->hasPermission($ability) ? true : false;
        });
    }
}
