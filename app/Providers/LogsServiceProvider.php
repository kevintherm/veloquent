<?php

namespace App\Providers;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class LogsServiceProvider extends ServiceProvider
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
        if ($this->app->runningInConsole()) {
            return;
        }

        $this->logHttpRequests();
        $this->logSlowQueries();
    }

    protected function logHttpRequests(): void
    {
        Event::listen(RequestHandled::class, function (RequestHandled $event) {
            $request = $event->request;
            $response = $event->response;

            // Only log API requests, and skip the log viewer endpoints themselves
            if (!$request->is('api/*') || $request->is('api/logs', 'api/logs/*')) {
                return;
            }

            $sensitiveKeys = ['password', 'password_confirmation', 'token', 'authorization'];
            $payload = $request->except($sensitiveKeys);

            // Truncate values
            array_walk_recursive($payload, function (&$value) {
                if (is_string($value) && Str::length($value) > 500) {
                    $value = Str::limit($value, 500);
                }
            });

            Log::info('HTTP_REQUEST', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'status' => $response->getStatusCode(),
                'ip' => $request->ip(),
                'user' => $request->user()->toArray(),
                'payload' => $payload,
                'duration' => defined('LARAVEL_START') ? floor((microtime(true) - LARAVEL_START) * 1000) : null,
            ]);
        });
    }

    protected function logSlowQueries(): void
    {
        DB::whenQueryingForLongerThan(1000, function ($connection, $event) {
            Log::warning('SLOW_QUERY', [
                'sql' => $event->sql,
                'bindings' => $event->bindings,
                'time' => $event->time,
                'connection' => $connection->getName(),
            ]);
        });
    }
}
