<?php

namespace App\Providers;

use App\Domain\Collections\Events\CollectionTruncated;
use App\Domain\Collections\Models\Collection;
use App\Domain\Emails\Models\EmailTemplate;
use App\Domain\QueryCompiler\Exceptions\InvalidRuleExpressionException;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Arr;
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
        $this->logModelChanges();
        $this->logApiRuleExceptions();
    }

    protected function logModelChanges(): void
    {
        Collection::updated(function (Collection $collection) {
            Log::info('COLLECTION_UPDATED', [
                'id' => $collection->id,
                'name' => $collection->name,
                'changes' => $collection->getChanges(),
                'original' => Arr::only($collection->getOriginal(), array_keys($collection->getChanges())),
                'user' => request()->user()?->id,
            ]);
        });

        EmailTemplate::updated(function (EmailTemplate $template) {
            Log::info('EMAIL_TEMPLATE_UPDATED', [
                'id' => $template->id,
                'collection_id' => $template->collection_id,
                'action' => $template->action,
                'changes' => $template->getChanges(),
                'original' => Arr::only($template->getOriginal(), array_keys($template->getChanges())),
                'user' => request()->user()?->id,
            ]);
        });

        Event::listen(CollectionTruncated::class, function (CollectionTruncated $event) {
            Log::info('COLLECTION_TRUNCATED', [
                'id' => $event->collection->id,
                'name' => $event->collection->name,
                'deleted_count' => $event->deletedCount,
                'user' => request()->user()?->id,
            ]);
        });
    }

    protected function logApiRuleExceptions(): void
    {
        Event::listen(InvalidRuleExpressionException::class, function (InvalidRuleExpressionException $exception) {
            Log::error('API_RULE_EXCEPTION', [
                'message' => $exception->getMessage(),
                'url' => request()->fullUrl(),
                'user' => request()->user()?->id,
                'trace' => Str::limit($exception->getTraceAsString(), 1000),
            ]);
        });
    }

    protected function logHttpRequests(): void
    {
        Event::listen(RequestHandled::class, function (RequestHandled $event) {
            $request = $event->request;
            $response = $event->response;

            // Only log API requests, and skip the log viewer endpoints themselves
            if (! $request->is('api/*') || $request->is('api/logs', 'api/logs/*')) {
                return;
            }

            $sensitiveKeys = ['password', 'password_confirmation', 'token', 'authorization', 'secret'];

            $payload = $request->except($sensitiveKeys);

            array_walk_recursive($payload, function (&$value, $key) use ($sensitiveKeys) {
                if (in_array(strtolower($key), $sensitiveKeys)) {
                    $value = '[REDACTED]';
                } elseif (is_string($value) && Str::length($value) > 500) {
                    $value = Str::limit($value, 500);
                }
            });

            Log::info('HTTP_REQUEST', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'status' => $response->getStatusCode(),
                'ip' => $request->ip(),
                'headers' => collect($request->headers->all())
                    ->except(['authorization', 'cookie', 'x-xsrf-token'])
                    ->map(fn ($v) => $v[0] ?? $v)
                    ->toArray(),
                'user' => $request->user()?->id,
                'payload' => $payload,
                'duration' => defined('LARAVEL_START') ? floor((microtime(true) - LARAVEL_START) * 1000) : null,
            ]);
        });
    }

    protected function logSlowQueries(): void
    {
        DB::whenQueryingForLongerThan(config('velo.logs.slow_query_threshold'), function ($connection, $event) {
            Log::warning('SLOW_QUERY', [
                'sql' => $event->sql,
                'bindings' => $event->bindings,
                'time' => $event->time,
                'connection' => $connection->getName(),
            ]);
        });
    }
}
