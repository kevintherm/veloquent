<?php

namespace App\Providers;

use App\Domain\Auth\Services\TokenAuthService;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use App\Infrastructure\Guards\TokenGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (config('app.force_https', false)) {
            URL::forceHttps(true);
        }

        URL::forceRootUrl(config('app.url'));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {}
}
