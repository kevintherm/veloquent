<?php

namespace App\Providers;

use App\Domain\Collections\Models\Collection;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Route::bind('collection', function ($value) {
            return Collection::findByIdCached($value)
                ?? Collection::findByNameCached($value)
                ?? abort(404, 'Collection not found');
        });

        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });
    }
}
