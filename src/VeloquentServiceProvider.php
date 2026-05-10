<?php

namespace Veloquent\Core;

use Illuminate\Support\ServiceProvider;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;
use Veloquent\Core\Domain\Auth\Services\TokenAuthService;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Infrastructure\Guards\TokenGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Veloquent\Core\Providers\LogsServiceProvider;
use Veloquent\Core\Domain\Realtime\Providers\RealtimeServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class VeloquentServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/velo.php', 'velo');
        $this->mergeConfigFrom(__DIR__ . '/../config/multitenancy.php', 'multitenancy');
        
        $this->app->bind(\Spatie\Multitenancy\Contracts\IsTenant::class, function ($app) {
            return $app->make(config('multitenancy.tenant_model'));
        });

        $veloAuth = config('velo.auth', []);
        config(['auth.guards' => array_merge(config('auth.guards', []), $veloAuth['guards'] ?? [])]);
        config(['auth.providers' => array_merge(config('auth.providers', []), $veloAuth['providers'] ?? [])]);
        config(['auth.defaults.guard' => $veloAuth['defaults']['guard'] ?? config('auth.defaults.guard')]);

        $this->app->register(LogsServiceProvider::class);
        $this->app->register(RealtimeServiceProvider::class);

        $this->registerGates();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerAuth();
        $this->registerMiddleware();
        $this->registerRoutes();
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'velo');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->registerRouteBindings();
        $this->registerRateLimiters();

        if (file_exists(__DIR__ . '/../routes/channels.php')) {
            require __DIR__ . '/../routes/channels.php';
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Veloquent\Core\Console\Commands\CreateTenantCommand::class,
                \Veloquent\Core\Console\Commands\DeleteTenantCommand::class,
                \Veloquent\Core\Console\Commands\ListTenantsCommand::class,
                \Veloquent\Core\Console\Commands\PurgeTenantCommand::class,
                \Veloquent\Core\Console\Commands\InstallCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/velo.php' => config_path('velo.php'),
                __DIR__ . '/../config/multitenancy.php' => config_path('multitenancy.php'),
            ], 'velo-config');

            $this->publishes([
                __DIR__ . '/../resources/dist' => public_path('vendor/velo'),
                __DIR__ . '/../resources/assets/favicon.ico' => public_path('vendor/velo/favicon.ico'),
                __DIR__ . '/../resources/assets/logo.svg' => public_path('vendor/velo/logo.svg'),
            ], 'velo-assets');

        }
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];
        $router->aliasMiddleware('superuser', \Veloquent\Core\Http\Middleware\SuperuserOnly::class);
        $router->aliasMiddleware('token.auth', \Veloquent\Core\Http\Middleware\TokenAuthMiddleware::class);
        $router->aliasMiddleware('needs.tenant', \Veloquent\Core\Http\Middleware\EnsureTenant::class);
    }

    protected function registerAuth(): void
    {
        Auth::extend('opaque_token', function ($app, $name, array $config) {
            return new TokenGuard(
                $app->make(TokenAuthService::class),
            );
        });
    }

    protected function registerGates(): void
    {
        Gate::define('manage-schema', fn ($user) => $user?->isSuperuser());

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
    }

    protected function registerRoutes(): void
    {
        Route::prefix(config('velo.api_prefix'))
            ->middleware(['api', 'needs.tenant', 'token.auth'])
            ->group(function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
            });

        Route::prefix(config('velo.admin_prefix'))
            ->middleware(['web', 'needs.tenant'])
            ->group(function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
            });

        if (file_exists(__DIR__ . '/../routes/landlord/api.php')) {
            Route::prefix('landlord/api')
                ->middleware('api')
                ->group(function () {
                    $this->loadRoutesFrom(__DIR__ . '/../routes/landlord/api.php');
                });
        }
    }

    protected function registerRouteBindings(): void
    {
        Route::bind('collection', function ($value) {
            if (! \Veloquent\Core\Infrastructure\Models\Tenant::current()) {
                abort(404, 'Tenant not found');
            }

            return Collection::findByIdCached($value)
                ?? Collection::findByNameCached($value)
                ?? abort(404, 'Collection not found');
        });
    }

    protected function registerRateLimiters(): void
    {
        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });
    }
}
