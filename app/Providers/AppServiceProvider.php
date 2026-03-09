<?php

namespace App\Providers;

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerGates();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    private function registerGates(): void
    {
        Gate::define('list-collections', function (?Record $user) {
            return $user && $user->getTable() === 'superusers';
        });

        Gate::define('view-collections', function (?Record $user) {
            return $user && $user->getTable() === 'superusers';
        });

        Gate::define('create-collections', function (?Record $user, array|Collection $data) {
            return $user && $user->getTable() === 'superusers' && $data['is_system'] === false;
        });

        Gate::define('update-collections', function (?Record $user, array|Collection $data) {
            return $user && $user->getTable() === 'superusers' && $data['is_system'] === false;
        });

        Gate::define('delete-collections', function (?Record $user, array|Collection $data) {
            return $user && $user->getTable() === 'superusers' && $data['is_system'] === false;
        });

        Gate::define('list-records', function (?Record $user, Collection $collection) {
            return ($user && $user->getTable() === 'superusers') || $collection->is_system === false;
        });

        Gate::define('view-records', function (?Record $user, Collection $collection) {
            return ($user && $user->getTable() === 'superusers') || $collection->is_system === false;
        });

        Gate::define('create-records', function (?Record $user, Collection $collection) {
            return ($user && $user->getTable() === 'superusers') || $collection->is_system === false;
        });

        Gate::define('update-records', function (?Record $user, Collection $collection) {
            return ($user && $user->getTable() === 'superusers') || $collection->is_system === false;
        });

        Gate::define('delete-records', function (?Record $user, Collection $collection) {
            return ($user && $user->getTable() === 'superusers') || $collection->is_system === false;
        });

    }
}
