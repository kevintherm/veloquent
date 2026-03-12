<?php

namespace App\Domain\Auth\Controllers;

use App\Domain\Auth\Services\JwtAuthService;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use App\Http\Requests\Auth\LoginRequest;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends ApiController
{
    public function __construct(
        private JwtAuthService $jwtService
    ) {}

    public function login(LoginRequest $request, Collection $collection): JsonResponse
    {
        if ($collection->type !== CollectionType::Auth) {
            return $this->errorResponse('This collection does not support authentication.', Response::HTTP_FORBIDDEN);
        }

        $credentials = $request->only('email', 'password');

        $user = Record::forCollection($collection)->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return $this->errorResponse('Invalid credentials.', Response::HTTP_UNAUTHORIZED);
        }

        $tokenData = $this->jwtService->generateToken($user);

        return $this->tokenResponse($tokenData);
    }

    public function logoutAll(): JsonResponse
    {
        Auth::logout();

        return $this->successResponse([], 'Logged out successfully.');
    }

    public function me(): JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            return $this->errorResponse('User not authenticated.', Response::HTTP_UNAUTHORIZED);
        }

        return $this->successResponse($user);
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => 'required|string|size:64',
        ]);

        $tokenData = $this->jwtService->refresh($request->input('refresh_token'));

        return $this->tokenResponse($tokenData);
    }

    /**
     * @param  array{token: string, expires_in: int, user: array, collection_name: string}  $tokenData
     */
    private function tokenResponse(array $tokenData, int $code = Response::HTTP_OK): JsonResponse
    {
        $cookie = new Cookie(
            name: 'refresh_token',
            value: $tokenData['refresh_token'],
            expire: $tokenData['refresh_token_expires_in'],
            path: '/',
            domain: null,
            secure: true,
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_STRICT,
        );

        return $this->successResponse(
            [
                'access_token' => $tokenData['token'],
                'refresh_token' => $tokenData['refresh_token'],
                'expires_in' => $tokenData['expires_in'],
                'collection_name' => $tokenData['collection_name'],
            ],
            'Success',
            $code,
            cookie: $cookie
        );
    }
}
