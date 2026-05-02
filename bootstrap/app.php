<?php

use Veloquent\Core\Domain\SchemaManagement\Exceptions\SchemaCorruptException;
use Veloquent\Core\Http\Middleware\SuperuserOnly;
use Veloquent\Core\Http\Middleware\TokenAuthMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Spatie\Multitenancy\Exceptions\NoCurrentTenant;
use Spatie\Multitenancy\Http\Middleware\NeedsTenant;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        [
            'prefix' => 'api',
            'middleware' => [TokenAuthMiddleware::class],
        ]
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend([
            HandleCors::class,
            NeedsTenant::class,
        ]);

        $middleware->throttleWithRedis();
        $middleware->redirectGuestsTo(function (Request $request): ?string {
            $apiPrefix = config('velo.api_prefix', 'api');
            if ($request->is("{$apiPrefix}/*") || $request->expectsJson()) {
                return null;
            }

            return '/';
        });

        $middleware->remove([
            ConvertEmptyStringsToNull::class,
        ]);

        $middleware->web(remove: [
            StartSession::class,
            ShareErrorsFromSession::class,
            ValidateCsrfToken::class,
        ]);

        $middleware->alias([
            'superuser' => SuperuserOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, $request) {
            if ($e instanceof NoCurrentTenant) {
                return abort(Response::HTTP_NOT_FOUND);
            }

            $apiPrefix = config('velo.api_prefix', 'api');
            if ($request->is("{$apiPrefix}/*")) {
                $errorResponse = static function (string $message, int $code, mixed $errors = null) {
                    return response()->json([
                        'message' => $message,
                        'errors' => $errors,
                    ], $code);
                };

                if ($e instanceof SchemaCorruptException) {
                    return response()->json([
                        'message' => $e->getMessage(),
                        'error_type' => 'SCHEMA_CORRUPT',
                        'activity' => $e->activity->value,
                        'collection_id' => $e->collectionId,
                    ], 409);
                }

                if ($e instanceof ValidationException) {
                    $firstError = collect($e->errors())->flatten()->first() ?? 'Validation error';

                    return $errorResponse($firstError, Response::HTTP_UNPROCESSABLE_ENTITY, $e->errors());
                }
                if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                    return $errorResponse('Resource not found', Response::HTTP_NOT_FOUND);
                }
                if ($e instanceof AuthenticationException) {
                    return $errorResponse($e->getMessage() ?: 'Unauthenticated', Response::HTTP_UNAUTHORIZED);
                }
                if ($e instanceof AuthorizationException) {
                    return $errorResponse('Unauthorized', Response::HTTP_FORBIDDEN);
                }
                if ($e instanceof HttpException) {
                    return $errorResponse($e->getMessage(), $e->getStatusCode());
                }

                return $errorResponse(
                    config('app.debug') ? $e->getMessage() : 'Something went wrong',
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    config('app.debug') ? [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => collect($e->getTrace())->take(100),
                    ] : null
                );
            }
        });
    })->create();
