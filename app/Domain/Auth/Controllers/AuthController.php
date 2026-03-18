<?php

namespace App\Domain\Auth\Controllers;

use App\Domain\Auth\Services\TokenAuthService;
use App\Domain\Collections\Enums\CollectionType;
use App\Domain\Collections\Models\Collection;
use App\Domain\Records\Models\Record;
use App\Http\Requests\Auth\LoginRequest;
use App\Infrastructure\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends ApiController
{
    public function __construct(
        private TokenAuthService $tokenService
    ) {}

    public function login(LoginRequest $request, Collection $collection): JsonResponse
    {
        if ($collection->type !== CollectionType::Auth) {
            return $this->errorResponse('This collection does not support authentication.', Response::HTTP_FORBIDDEN);
        }

        $credentials = $request->only('email', 'password');

        $user = Record::of($collection)->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return $this->errorResponse('Invalid credentials.', Response::HTTP_UNAUTHORIZED);
        }

        $tokenData = $this->tokenService->generateToken($user);

        return $this->tokenResponse($tokenData);
    }

    public function logoutAll(Collection $collection): JsonResponse
    {
        /** @var Record|null $user */
        $user = Auth::user();

        if ($user && ! $this->userMatchesCollection($user, $collection)) {
            return $this->errorResponse('User not authenticated.', Response::HTTP_UNAUTHORIZED);
        }

        if ($user?->collection?->id) {
            $this->tokenService->revokeRecordTokens($user->collection->id, $user->id);
        }

        return $this->successResponse([], 'Logged out successfully.');
    }

    public function me(Collection $collection): JsonResponse
    {
        /** @var Record|null $user */
        $user = Auth::user();

        if (! $user || ! $this->userMatchesCollection($user, $collection)) {
            return $this->errorResponse('User not authenticated.', Response::HTTP_UNAUTHORIZED);
        }

        return $this->successResponse($user);
    }

    private function userMatchesCollection(Record $user, Collection $collection): bool
    {
        return $user->collection?->id === $collection->id;
    }

    /**
     * @param  array{token: string, expires_in: int, collection_name: string}  $tokenData
     */
    private function tokenResponse(array $tokenData, int $code = Response::HTTP_OK): JsonResponse
    {
        return $this->successResponse(
            [
                'token' => $tokenData['token'],
                'expires_in' => $tokenData['expires_in'],
                'collection_name' => $tokenData['collection_name'],
            ],
            'Success',
            $code
        );
    }
}
