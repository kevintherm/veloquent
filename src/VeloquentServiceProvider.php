<?php

namespace Veloquent\Core;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use \Veloquent\Core\Support\Models\Tenant;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Multitenancy\Contracts\IsTenant;
use Veloquent\Core\Domain\Hooks\HookRunner;
use Veloquent\Core\Domain\Hooks\HookRegistry;
use Veloquent\Core\Support\Guards\TokenGuard;
use Veloquent\Core\Domain\Settings\AiSettings;
use Veloquent\Core\Domain\Settings\EmailSettings;
use Veloquent\Core\Providers\LogsServiceProvider;
use Veloquent\Core\Console\Commands\InstallCommand;
use Veloquent\Core\Domain\Settings\GeneralSettings;
use Veloquent\Core\Domain\Settings\StorageSettings;
use Veloquent\Core\Support\Settings\SettingsContainer;
use Veloquent\Core\Console\Commands\ListTenantsCommand;
use Veloquent\Core\Console\Commands\PurgeTenantCommand;
use Veloquent\Core\Domain\Ai\Hooks\EvaluateChatApiRule;
use Veloquent\Core\Console\Commands\CreateTenantCommand;
use Veloquent\Core\Console\Commands\DeleteTenantCommand;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Support\Http\Middleware\EnsureTenant;
use Veloquent\Core\Console\Commands\ExtractTenantCommand;
use Veloquent\Core\Domain\Auth\Services\TokenAuthService;
use Veloquent\Core\Support\Http\Middleware\SuperuserOnly;
use Veloquent\Core\Support\Http\Middleware\TokenAuthMiddleware;
use Veloquent\Core\Domain\Realtime\Providers\RealtimeServiceProvider;

class VeloquentServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/velo.php', 'velo');
        $this->mergeConfigFrom(__DIR__ . '/../config/multitenancy.php', 'multitenancy');
        
        $this->app->bind(IsTenant::class, function ($app) {
            return $app->make(config('multitenancy.tenant_model'));
        });

        $this->app->singleton(HookRegistry::class);
        $this->app->singleton(HookRunner::class);

        $veloAuth = config('velo.auth', []);
        config(['auth.guards' => array_merge(config('auth.guards', []), $veloAuth['guards'] ?? [])]);
        config(['auth.providers' => array_merge(config('auth.providers', []), $veloAuth['providers'] ?? [])]);
        config(['auth.defaults.guard' => $veloAuth['defaults']['guard'] ?? config('auth.defaults.guard')]);

        config(['settings.cache.enabled' => env('SETTINGS_CACHE_ENABLED', true)]);
        config(['settings.cache.encrypted' => env('SETTINGS_CACHE_ENCRYPTED', false)]);

        $this->app->singleton(SettingsContainer::class, function () {
            $container = new SettingsContainer();
            $container->register(GeneralSettings::class);
            $container->register(StorageSettings::class);
            $container->register(EmailSettings::class);
            $container->register(AiSettings::class);
            return $container;
        });

        $this->app->singleton(GeneralSettings::class, fn () => GeneralSettings::load());
        $this->app->singleton(StorageSettings::class, fn () => StorageSettings::load());
        $this->app->singleton(EmailSettings::class, fn () => EmailSettings::load());
        $this->app->singleton(AiSettings::class, fn () => AiSettings::load());

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

        $this->app->make(HookRegistry::class)->register(
            'ai.generating',
            EvaluateChatApiRule::class
        );

        if (file_exists(__DIR__ . '/../routes/channels.php')) {
            require __DIR__ . '/../routes/channels.php';
        }

        if (file_exists(base_path('hooks/hooks.php'))) {
            require base_path('hooks/hooks.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateTenantCommand::class,
                DeleteTenantCommand::class,
                ListTenantsCommand::class,
                PurgeTenantCommand::class,
                InstallCommand::class,
                ExtractTenantCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/velo.php' => config_path('velo.php'),
                __DIR__ . '/../config/multitenancy.php' => config_path('multitenancy.php'),
                __DIR__ . '/Domain/Hooks/stubs/hooks.php.stub' => base_path('hooks/hooks.php'),
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
        $router->aliasMiddleware('superuser', SuperuserOnly::class);
        $router->aliasMiddleware('token.auth', TokenAuthMiddleware::class);
        $router->aliasMiddleware('needs.tenant', EnsureTenant::class);
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
        Gate::define('manage-schema', fn ($user = null) => $user?->isSuperuser());

        foreach (['list', 'view'] as $action) {
            Gate::define("{$action}-collections", fn ($user = null) => $user?->isSuperuser());
        }

        foreach (['create', 'update', 'delete'] as $action) {
            Gate::define("{$action}-collections", function ($user = null, $data = null) use ($action) {
                if (! $user?->isSuperuser()) {
                    return false;
                }

                $isSystem = $data instanceof Collection ? $data->is_system : ($data['is_system'] ?? false);
                return ! $isSystem;
            });
        }

        Gate::define('truncate-collections', fn ($user = null, ?Collection $collection = null) => $user?->isSuperuser() && $collection?->is_system === false);

        foreach (['list', 'view', 'create', 'update', 'delete'] as $action) {
            Gate::define("{$action}-records", fn ($user = null, ?Collection $collection = null) => $user?->isSuperuser() || $collection?->is_system === false);
        }
    }

    protected function registerRoutes(): void
    {
        Route::prefix(config('velo.api_prefix'))
            ->middleware(['needs.tenant', 'api', 'throttle:api', 'token.auth'])
            ->group(function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
            });

        Route::prefix(config('velo.admin_prefix'))
            ->middleware(['needs.tenant', 'web'])
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
            if (! Tenant::current()) {
                abort(404, 'Tenant not found');
            }

            if (Str::isUlid($value)) {
                $collection = Collection::findByIdCached($value);
                if ($collection) {
                    return $collection;
                }
            }

            return Collection::findByNameCached($value)
                ?? abort(404, 'Collection not found');
        });
    }

    protected function registerRateLimiters(): void
    {
        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(240)->by($request->user()?->id ?: $request->ip());
        });
    }
}
