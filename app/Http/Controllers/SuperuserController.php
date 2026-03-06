<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Infrastructure\Http\Controllers\ApiController;
use App\Models\Superuser;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class SuperuserController extends ApiController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $token = JWTAuth::attempt($request->only('email', 'password'));

        if (! $token) {
            return $this->errorResponse('Invalid credentials.', Response::HTTP_UNAUTHORIZED);
        }

        return $this->tokenResponse($token);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $superuser = Superuser::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        $token = JWTAuth::fromUser($superuser);

        return $this->tokenResponse($token, Response::HTTP_CREATED);
    }

    public function logout(): JsonResponse
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return $this->successResponse([], 'Logged out successfully.');
    }

    public function me(): JsonResponse
    {
        return $this->successResponse(JWTAuth::user());
    }

    public function refresh(): JsonResponse
    {
        return $this->tokenResponse(JWTAuth::refresh(JWTAuth::getToken()));
    }

    private function tokenResponse(string $token, int $code = Response::HTTP_OK): JsonResponse
    {
        return $this->successResponse([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => JWTAuth::user(),
        ], 'Success', $code);
    }
}
