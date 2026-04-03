<?php

namespace App\Providers;

use App\Domain\Docs\DocsManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class DocumentationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DocsManager::class, function () {
            return new DocsManager;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerRoutes();

        View::composer('docs.viewer', function ($view) {
            $view->with('sidebarCategories', app(DocsManager::class)->getSidebar());
        });
    }

    /**
     * Register the documentation routes.
     */
    protected function registerRoutes(): void
    {
        Route::middleware('web')
            ->group(base_path('routes/docs.php'));
    }
}
