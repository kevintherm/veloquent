<?php

use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use App\Exceptions\JwtException;
use App\Http\Middleware\JwtMiddleware;
use App\Infrastructure\Traits\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
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

            Route::bind('record', function ($value, $route) {
                $collection = $route->parameter('collection');
                if (! $collection) {
                    throw new ModelNotFoundException('Collection not found');
                }

                return Record::forCollection($collection)->findOrFail($value);
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(JwtMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*')) {
                return (new class
                {
                    use ApiResponse;

                    public function handle($e)
                    {
                        if ($e instanceof ValidationException) {
                            return $this->errorResponse('Validation error', Response::HTTP_UNPROCESSABLE_ENTITY, $e->errors());
                        }
                        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                            return $this->errorResponse('Resource not found', Response::HTTP_NOT_FOUND);
                        }
                        if ($e instanceof AuthenticationException) {
                            return $this->errorResponse('Unauthenticated', Response::HTTP_UNAUTHORIZED);
                        }
                        if ($e instanceof AuthorizationException) {
                            return $this->errorResponse('Unauthorized', Response::HTTP_FORBIDDEN);
                        }
                        if ($e instanceof JwtException) {
                            return $this->errorResponse($e->getMessage(), Response::HTTP_UNAUTHORIZED);
                        }
                        if ($e instanceof HttpException) {
                            return $this->errorResponse($e->getMessage(), $e->getStatusCode());
                        }

                        return $this->errorResponse(
                            config('app.debug') ? $e->getMessage() : 'Something went wrong',
                            Response::HTTP_INTERNAL_SERVER_ERROR,
                            config('app.debug') ? [
                                'exception' => get_class($e),
                                'file' => $e->getFile(),
                                'line' => $e->getLine(),
                                'trace' => collect($e->getTrace())->take(10),
                            ] : null
                        );
                    }
                })->handle($e);
            }
        });
    })->create();
