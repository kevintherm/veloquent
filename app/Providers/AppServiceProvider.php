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

        $this->registerGates();
        $this->registerAuth();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {}

    private function registerAuth(): void
    {
        Auth::extend('opaque_token', function ($app, $name, array $config) {
            return new TokenGuard(
                $app->make(TokenAuthService::class),
                $app->make('request'),
            );
        });
    }

    private function registerGates(): void
    {
        foreach (['list', 'view'] as $action) {
            Gate::define("{$action}-collections", fn (?Record $user) => $user?->isSuperuser());
        }

        foreach (['create', 'update', 'delete'] as $action) {
            Gate::define("{$action}-collections", fn (?Record $user, array|Collection $data) => $user?->isSuperuser() && ($data['is_system'] ?? false) === false);
        }

        Gate::define('truncate-collections', fn (?Record $user, Collection $collection) => $user?->isSuperuser() && $collection->is_system === false);

        foreach (['list', 'view', 'create', 'update', 'delete'] as $action) {
            Gate::define("{$action}-records", fn (?Record $user, Collection $collection) => $user?->isSuperuser() || $collection->is_system === false);
        }

        Gate::define('manage-schema', fn ($user) => $user?->isSuperuser());
    }
}
