<?php

use App\Domain\Collections\Models\Collection;
use App\Http\Middleware\TokenAuthMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::bind('collection', function ($value) {
                return Collection::where('id', $value)->orWhere('name', $value)->firstOrFail();
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(TokenAuthMiddleware::class);
        $middleware->remove(ConvertEmptyStringsToNull::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*')) {
                $errorResponse = static function (string $message, int $code, mixed $errors = null) {
                    return response()->json([
                        'message' => $message,
                        'errors' => $errors,
                    ], $code);
                };

                if ($e instanceof ValidationException) {
                    return $errorResponse('Validation error', Response::HTTP_UNPROCESSABLE_ENTITY, $e->errors());
                }
                if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                    return $errorResponse('Resource not found', Response::HTTP_NOT_FOUND);
                }
                if ($e instanceof AuthenticationException) {
                    return $errorResponse('Unauthenticated', Response::HTTP_UNAUTHORIZED);
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
