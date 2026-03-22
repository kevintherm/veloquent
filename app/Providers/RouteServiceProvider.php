<?php

namespace App\Providers;

use App\Domain\Collections\Models\Collection;
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
            return Collection::where('id', $value)->orWhere('name', $value)->firstOrFail();
        });
    }
}
