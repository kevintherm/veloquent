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
use Veloquent\Core\Support\Guards\TokenGuard;
use Veloquent\Core\Domain\Settings\AiSettings;
use Veloquent\Core\Domain\Ai\Contracts\AiService;
use Veloquent\Core\Domain\Settings\EmailSettings;
use Veloquent\Core\Providers\LogsServiceProvider;
use Veloquent\Core\Domain\Hooks\DefaultHookRunner;
use Veloquent\Core\Console\Commands\InstallCommand;
use Veloquent\Core\Domain\Settings\GeneralSettings;
use Veloquent\Core\Domain\Settings\StorageSettings;
use Veloquent\Core\Domain\Hooks\DefaultHookRegistry;
use Veloquent\Core\Domain\Hooks\Contracts\HookRunner;
use Veloquent\Core\Domain\Settings\RateLimitSettings;
use Veloquent\Core\Support\Settings\SettingsContainer;
use Veloquent\Core\Console\Commands\ListTenantsCommand;
use Veloquent\Core\Console\Commands\PurgeTenantCommand;
use Veloquent\Core\Domain\Ai\Hooks\EvaluateChatApiRule;
use Veloquent\Core\Domain\Ai\Services\DefaultAiService;
use Veloquent\Core\Domain\Hooks\Contracts\HookRegistry;
use Veloquent\Core\Domain\OAuth\Contracts\OAuthService;
use Veloquent\Core\Console\Commands\CreateTenantCommand;
use Veloquent\Core\Console\Commands\DeleteTenantCommand;
use Veloquent\Core\Domain\Ai\Hooks\WatchMaliciousPrompt;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Support\Http\Middleware\EnsureTenant;
use Veloquent\Core\Console\Commands\ExtractTenantCommand;
use Veloquent\Core\Support\Http\Middleware\SuperuserOnly;
use Veloquent\Core\Domain\Auth\Contracts\TokenAuthService;
use Veloquent\Core\Domain\OAuth\Services\DefaultOAuthService;
use Veloquent\Core\Support\Http\Middleware\TokenAuthMiddleware;
use Veloquent\Core\Domain\Auth\Services\DefaultTokenAuthService;
use Veloquent\Core\Console\Commands\PruneExpiredAuthTokensCommand;
use Veloquent\Core\Domain\Realtime\Providers\RealtimeServiceProvider;
use Veloquent\Core\Domain\SchemaManagement\Contracts\CollectionSyncService;
use Veloquent\Core\Domain\SchemaManagement\Services\DefaultCollectionSyncService;

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

        $this->app->singleton(DefaultHookRegistry::class);
        $this->app->singletonIf(HookRegistry::class, function ($app) {
            return $app->make(DefaultHookRegistry::class);
        });

        $this->app->singleton(DefaultHookRunner::class);
        $this->app->singletonIf(HookRunner::class, function ($app) {
            return $app->make(DefaultHookRunner::class);
        });

        $this->app->singleton(DefaultTokenAuthService::class);
        $this->app->singletonIf(TokenAuthService::class, function ($app) {
            return $app->make(DefaultTokenAuthService::class);
        });

        $this->app->singleton(DefaultAiService::class);
        $this->app->singletonIf(AiService::class, function ($app) {
            return $app->make(DefaultAiService::class);
        });

        $this->app->singleton(DefaultOAuthService::class);
        $this->app->singletonIf(OAuthService::class, function ($app) {
            return $app->make(DefaultOAuthService::class);
        });

        $this->app->singleton(DefaultCollectionSyncService::class);
        $this->app->singletonIf(CollectionSyncService::class, function ($app) {
            return $app->make(DefaultCollectionSyncService::class);
        });

        $veloAuth = config('velo.auth', []);
        config(['auth.guards' => array_merge(config('auth.guards', []), $veloAuth['guards'] ?? [])]);
        config(['auth.providers' => array_merge(config('auth.providers', []), $veloAuth['providers'] ?? [])]);
        config(['auth.defaults.guard' => $veloAuth['defaults']['guard'] ?? config('auth.defaults.guard')]);

        config(['settings.cache.enabled' => config('velo.settings.cache.enabled', true)]);
        config(['settings.cache.encrypted' => config('velo.settings.cache.encrypted', false)]);

        $this->app->singleton(SettingsContainer::class, function () {
            $container = new SettingsContainer();
            $container->register(GeneralSettings::class);
            $container->register(StorageSettings::class);
            $container->register(EmailSettings::class);
            $container->register(AiSettings::class);
            $container->register(RateLimitSettings::class);
            return $container;
        });

        $this->app->singleton(GeneralSettings::class, fn () => GeneralSettings::load());
        $this->app->singleton(StorageSettings::class, fn () => StorageSettings::load());
        $this->app->singleton(EmailSettings::class, fn () => EmailSettings::load());
        $this->app->singleton(AiSettings::class, fn () => AiSettings::load());
        $this->app->singleton(RateLimitSettings::class, fn () => RateLimitSettings::load());

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

        $this->app->make(HookRegistry::class)->register(
            'ai.generating',
            WatchMaliciousPrompt::class
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
                PruneExpiredAuthTokensCommand::class,
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
            Gate::define("{$action}-collections", function ($user = null, $data = null) {
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
            $settings = app(RateLimitSettings::class);
            if (!$settings->rate_limit_enabled) {
                return Limit::none();
            }

            if ($request->user()?->isSuperuser()) {
                return Limit::none();
            }

            $rule = collect($settings->rate_limit_rules)->firstWhere('label', '*:otp');

            return $rule ? ($this->resolveLimit($rule, $request) ?? Limit::none()) : Limit::none();
        });

        RateLimiter::for('auth', function (Request $request) {
            $settings = app(RateLimitSettings::class);
            if (!$settings->rate_limit_enabled) {
                return Limit::none();
            }

            if ($request->user()?->isSuperuser()) {
                return Limit::none();
            }

            $rule = collect($settings->rate_limit_rules)->firstWhere('label', '*:auth');

            return $rule ? ($this->resolveLimit($rule, $request) ?? Limit::none()) : Limit::none();
        });

        RateLimiter::for('api', function (Request $request) {
            $settings = app(RateLimitSettings::class);
            if (!$settings->rate_limit_enabled) {
                return Limit::none();
            }

            if ($request->user()?->isSuperuser()) {
                return Limit::none();
            }

            $rules = collect($settings->rate_limit_rules)
                ->reject(fn (array $rule) => in_array($rule['label'], ['*:otp', '*:auth']));

            $limits = [];
            foreach ($rules as $rule) {
                if ($this->ruleMatchesRequest($rule, $request)) {
                    $limit = $this->resolveLimit($rule, $request);
                    if ($limit) {
                        $limits[] = $limit;
                    }
                }
            }

            return $limits ?: Limit::none();
        });
    }

    /**
     * Resolve the rate limit instance for a given rule configuration and request.
     */
    protected function resolveLimit(array $rule, Request $request): ?Limit
    {
        $audience = $rule['audience'] ?? 'all';
        $user = $request->user();

        if ($audience === 'guest' && $user) {
            return null;
        }
        if ($audience === 'auth' && !$user) {
            return null;
        }

        $byKey = ($audience === 'guest')
            ? $request->ip()
            : (($audience === 'auth') ? $user->id : ($user?->id ?: $request->ip()));

        return Limit::perMinutes($rule['decay_minutes'], $rule['max_attempts'])->by($byKey);
    }

    /**
     * Determine if a rate limit rule pattern/tag matches the incoming request context.
     */
    protected function ruleMatchesRequest(array $rule, Request $request): bool
    {
        $label = $rule['label'];

        if ($label === '*') {
            return true;
        }

        if ($label === '*:create') {
            return $request->isMethod('POST') && !Str::contains($request->getPathInfo(), '/auth/');
        }

        if ($label === '*:update') {
            return ($request->isMethod('PUT') || $request->isMethod('PATCH')) && !Str::contains($request->getPathInfo(), '/auth/');
        }

        if ($label === '*:delete') {
            return $request->isMethod('DELETE') && !Str::contains($request->getPathInfo(), '/auth/');
        }

        if ($label === '*:view') {
            return $request->isMethod('GET') && Str::contains($request->route()?->getName() ?? '', '.show');
        }

        if ($label === '*:list') {
            return $request->isMethod('GET') && Str::contains($request->route()?->getName() ?? '', '.index');
        }

        $pattern = '/' . ltrim($label, '/');
        $path = '/' . ltrim($request->getPathInfo(), '/');
        if (str_ends_with($pattern, '/')) {
            $pattern .= '*';
        }

        return Str::is($pattern, $path);
    }
}
