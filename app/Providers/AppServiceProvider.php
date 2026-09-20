<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());

        Gate::before(fn ($user, string $ability) => $user->hasRole('super_admin') ? true : null);

        RateLimiter::for('recognition', function (Request $request) {
            $terminal = $request->attributes->get('terminal');

            return Limit::perMinute(240)->by($terminal?->id ?? $request->ip());
        });
    }
}
