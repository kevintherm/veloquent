<?php

namespace App\Http\Controllers;

use App\Domain\Auth\Models\DynamicAuth;
use App\Domain\Auth\Services\DynamicAuthService;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Infrastructure\Http\Controllers\ApiController;
use App\Models\Superuser;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends ApiController
{
    public function __construct(
        private DynamicAuthService $dynamicAuthService
    ) {}

    public function login(LoginRequest $request, ?string $collection = null): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if ($collection) {
            // Dynamic user authentication
            $user = $this->dynamicAuthService->authenticate($credentials, $collection);

            if (! $user) {
                return $this->errorResponse('Invalid credentials.', Response::HTTP_UNAUTHORIZED);
            }

            $token = JWTAuth::fromUser($user);

            return $this->tokenResponse($token, $user, 'dynamic');
        }

        // Default authentication (fallback)
        $token = JWTAuth::attempt($credentials);

        if (! $token) {
            return $this->errorResponse('Invalid credentials.', Response::HTTP_UNAUTHORIZED);
        }

        $user = JWTAuth::user();

        return $this->tokenResponse($token, $user, 'default');
    }

    public function register(RegisterRequest $request, ?string $collection = null): JsonResponse
    {
        if ($collection) {
            // Dynamic user registration
            $user = $this->dynamicAuthService->register($request->validated(), $collection);
            $token = JWTAuth::fromUser($user);

            return $this->tokenResponse($token, $user, 'dynamic', Response::HTTP_CREATED);
        }

        // Default registration (fallback)
        $user = DynamicAuth::create($request->validated());
        $token = JWTAuth::fromUser($user);

        return $this->tokenResponse($token, $user, 'default', Response::HTTP_CREATED);
    }

    public function logout(): JsonResponse
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return $this->successResponse([], 'Logged out successfully.');
    }

    public function me(): JsonResponse
    {
        $user = JWTAuth::user();

        return $this->successResponse([
            'user' => $user,
            'type' => $user instanceof Superuser ? 'superuser' : 'dynamic',
        ]);
    }

    public function refresh(): JsonResponse
    {
        $token = JWTAuth::refresh(JWTAuth::getToken());
        $user = JWTAuth::user();

        return $this->tokenResponse($token, $user, 'refreshed');
    }

    private function tokenResponse(string $token, $user, string $authType, int $code = Response::HTTP_OK): JsonResponse
    {
        return $this->successResponse([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => $user,
            'auth_type' => $authType,
        ], 'Success', $code);
    }
}
